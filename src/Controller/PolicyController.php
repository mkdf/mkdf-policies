<?php
namespace MKDF\Policies\Controller;

use MKDF\Policies\Repository\PoliciesRepositoryInterface;
use MKDF\Policies\Repository\PoliciesRepository;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManager;
use MKDF\Datasets\Service\DatasetPermissionManagerInterface;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Session\Container;


class PolicyController extends AbstractActionController
{
    private $_config;
    private $viewRenderer;
    private $_repository;
    private $_policyRepository;
    private $_dataset_repository;
    private $_permissionManager;

    public function __construct(PoliciesRepositoryInterface $policyRepository, MKDFStreamRepositoryInterface $repository, MKDFDatasetRepositoryInterface $datasetRepository, DatasetPermissionManager $permissionManager, array $config, $viewRenderer)
    {
        $this->_config = $config;
        $this->viewRenderer = $viewRenderer;
        $this->_repository = $repository;
        $this->_policyRepository = $policyRepository;
        $this->_dataset_repository = $datasetRepository;
        $this->_permissionManager = $permissionManager;
    }

    private function _createDummyLicense() {
        /*
         *  {
                "@context": "http://www.w3.org/ns/odrl.jsonld",
                "@type": "Policy",
                "uid": "http://example.com/policy:8888",
                "profile": "http://spice.kmi.open.ac.uk/odrl/profile:1",
                "target": "http://spice.kmi.open.ac.uk/dataset/dataset-id",
                "assigner": " http://spice.kmi.open.ac.uk/dataset/dataset-id/admin",
                "assignee": "http:// spice.kmi.open.ac.uk/people/user12345"
                “title”: “Licence title”,
                “text”: “Licence text”,
                “active”: true,
                “created-time”: "12312321323”,
                “active-from”: “5/7/2022”,
                “active-until”: “7/7/2022”,
                "permission": [
                    { "action": ["play"] },
                    { "action": ["distribute", “advertise”], refinement: {...}  },
                    { "action": ["copy"], constraint: {...}, duty: {...} },
                ]},…], “obligation”: [ { “action”: [ “attribute” ], refinement: { } }]
            }
         */
        $dummyLicense = [
            "@context" => "http://www.w3.org/ns/odrl.jsonld",
            "@type" => "Policy",
            "uid" => "http://example.com/policy:8888",
            "profile" => "http://spice.kmi.open.ac.uk/odrl/profile:1",
            "target" => "http://spice.kmi.open.ac.uk/dataset/dataset-id",
            "assigner" => "http://spice.kmi.open.ac.uk/dataset/dataset-id/admin",
            "assignee" => "http://spice.kmi.open.ac.uk/people/user12345",
            "title" => "Licence title",
            "text" => "Licence text",
            "active" => true,
            "created-time" => "12312321323",
            "active-from" => "5/7/2022",
            "active-until" => "7/7/2022",
            "permission" => [
                [
                    "action" => ["play"]
                ],
                [
                    "action" => ["distribute", "advertise"],
                    "refinement" => [],
                ],
                [
                    "action" => ["copy"],
                    "constraint" => [],
                    "duty" => []
                ],
            ],
            "obligation" => [
                [
                    "action" => ["distribute", "advertise"],
                    "refinement" => [],
                ],
            ]
        ];
        return $dummyLicense;
    }

    public function indexAction() {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_dataset_repository->findDataset($id);
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;
        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        $dummyLicense = $this->_createDummyLicense();

        $actions = [];

        if ($can_view && $can_edit) {

            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [
                    [
                        'class' => '',
                        'type' => 'primary',
                        'icon' => 'create',
                        'label' => 'Create a new custom license',
                        'target' => 'dataset-policies',
                        'params' => [
                            'action' => 'create',
                            'id' => $id
                        ]
                    ],
                    [
                        'class' => '',
                        'type' => 'warning',
                        'icon' => 'view',
                        'label' => 'Select and apply a standard license',
                        'target' => 'dataset-policies',
                        'params' => [
                            'action' => 'select',
                            'id' => $id
                        ]
                    ]
                ]
            ];

            $license = $this->_policyRepository->getDatasetUserLicense($dataset->uuid, 'all');

            return new ViewModel([
                'messages' => $messages,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'license' => $license['dataset']['active'][0],
                'history' => $license['dataset']['inactive'],
            ]);
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    public function selectAction() {
        $user_id = $this->currentUser()->getId();
        $user_email = $this->currentUser()->getEmail();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_dataset_repository->findDataset($id);
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        $actions = [];

        if ($can_view && $can_edit) {
            $licenses = json_decode($this->getLicenses());

            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'licenses' => $licenses,
                'assigner' => $user_email
            ]);
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    public function createAction() {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_dataset_repository->findDataset($id);
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        $actions = [];

        if ($can_view && $can_edit) {

            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
            ]);
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    public function libraryAction() {
        $uid = $this->params()->fromRoute('uid', null);
        $search = null;
        if (!is_null($uid)) {
            $search = [
                '_id' => $uid
            ];
        }
        $licenses = json_decode($this->getLicenses($search));
        return new JsonModel($licenses);
    }

    public function applyAction() {
        $user_id = $this->currentUser()->getId();
        $user_email = $this->currentUser()->getEmail();
        $id = (int) $this->params()->fromRoute('id', 0);
        $token = $this->params()->fromQuery('token', null);
        $license = $this->params()->fromQuery('license', null);
        $dataset = $this->_dataset_repository->findDataset($id);
        //$permissions = $this->_repository->findDatasetPermissions($id);
        $message = "Dataset: " . $id;
        $messages = [];
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            foreach($flashMessenger->getMessages() as $flashMessage) {
                $messages[] = [
                    'type' => 'warning',
                    'message' => $flashMessage
                ];
            }
        }
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);

        $actions = [];

        if ($can_view && $can_edit) {
            if (is_null($license)) {
                $this->flashMessenger()->addMessage('Error: No license specified');
                return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
            }

            if (!is_null($token)) {
                $container = new Container('apply_license');
                $valid_token = ($container->apply_token == $token);
                if ($valid_token) {  // Apply license here...
                    //Retrieve license
                    $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $dataset->uuid), True);
                    if (count($metadataResponse) === 0) {
                        // list is empty.
                        $metadata = [];
                    }
                    else {
                        $metadata = $metadataResponse[0];
                    }
                    $newMetadata = $this->addLicenseToMetadata($dataset->uuid, $license, $user_email, 'all', $metadata);
                    $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($newMetadata), $metadata['_id']);
                    $this->flashMessenger()->addMessage('The license has been applied to the dataset');
                }
                else {
                    $this->flashMessenger()->addMessage('Error: Invalid token. The license was not applied.');
                }
                return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
            }
            else {
                $token = uniqid(true);
                $container = new Container('apply_license');
                $container->apply_token = $token;
                return new ViewModel([
                    'messages' => $messages,
                    'dataset' => $dataset,
                    'features' => $this->datasetsFeatureManager()->getFeatures($id),
                    'actions' => $actions,
                    'can_edit' => $can_edit,
                    'can_read' => $can_read,
                    'token' => $token,
                    'license' => $license
                ]);
            }
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    private function addLicenseToMetadata($datasetUuid, $licenseId, $assigner, $assignee, $metadata) {
        if (!isset($metadata['policy'])) {
            $metadata['policy'] =
                [
                    'dataset' => [
                        'active' => [],
                        'inactive' => []
                    ],
                    'document' => [
                        'active' => [],
                        'inactive' => []
                    ],
                    'file' => [
                        'active' => [],
                        'inactive' => []
                    ]
                ];
        }


        $licenseReplaced = False;
        $nowTime = time();
        $keysToRemove = [];
        foreach ($metadata['policy']['dataset']['active'] as $key => $item) {
            if (isset($item)) {
                if (isset($item['odrl:assignee']) && isset($item['active'])) {
                    if ($item['odrl:assignee'] == $assignee && $item['active']) {
                        // TODO - CHECK LICENSE SCHEDULING (from-to)
                        $item['active'] = False;
                        $item['modified-time'] = $nowTime;
                        $item['schema:validUntil'] = $nowTime;
                        array_unshift($metadata['policy']['dataset']['inactive'], $item);
                        array_unshift($keysToRemove, $key);
                        $licenseReplaced = True;
                    }
                }
            }
        }
        foreach ($keysToRemove as $removeKey) {
            array_splice($metadata['policy']['dataset']['active'], $removeKey, 1);
        }
        $search = [
            '_id' => $licenseId
        ];
        $licenseBody = json_decode($this->getLicenses($search, False),True)[0];
        $licenseBody['odrl:assignee'] = $assignee;
        $licenseBody['odrl:assigner'] = $assigner;
        $licenseBody['odrl:target'] = $datasetUuid; // FIXME - dataset URI here
        $licenseBody['active'] = True;
        $licenseBody['created-time'] = $nowTime;
        $licenseBody['schema:validFrom'] = $nowTime;
        $licenseBody['schema:validUntil'] = 7500000000; // 185 years away
        array_unshift($metadata['policy']['dataset']['active'], $licenseBody);

        // TODO - build license object with assignee, dates and other appropriate metadata
        // created-time
        // valid-from
        // valid-to

        return $metadata;
    }

    private function getLicenses($search = null, $creationCheck = True) {
        $licensesDataset = $this->_config['mkdf-policies']['policies-dataset'];
        $licensesKey = $this->_config['mkdf-policies']['policies-key'];

        if ($creationCheck) {
            $licensesDatasetExists = $this->_repository->getStreamExists($licensesDataset);
            if (!$licensesDatasetExists) {
                $this->_repository->createDataset($licensesDataset,$licensesKey);
            }
        }

        if (is_null($search)){
            $queryJSON = '{}';
        }
        else {
            $query = $search;
            $queryJSON = json_encode($query);
        }
        return $this->_repository->getDocuments($licensesDataset,500,$licensesKey,$queryJSON);
    }
}