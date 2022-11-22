<?php
namespace MKDF\Policies\Controller;

use MKDF\Policies\Repository\PoliciesRepositoryInterface;
use MKDF\Policies\Repository\PoliciesRepository;
use MKDF\File\Repository\MKDFFileRepositoryInterface;
use MKDF\File\Repository\MKDFFileRepository;
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
    private $_fileRepository;
    private $_permissionManager;

    public function __construct(PoliciesRepositoryInterface $policyRepository, MKDFStreamRepositoryInterface $repository, MKDFDatasetRepositoryInterface $datasetRepository, MKDFFileRepositoryInterface $fileRepository, DatasetPermissionManager $permissionManager, array $config, $viewRenderer)
    {
        $this->_config = $config;
        $this->viewRenderer = $viewRenderer;
        $this->_repository = $repository;
        $this->_policyRepository = $policyRepository;
        $this->_dataset_repository = $datasetRepository;
        $this->_fileRepository = $fileRepository;
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
        $typeParam = $this->params()->fromQuery('type', dataset);
        $assigneeParam = $this->params()->fromQuery('assignee', 'all');
        $licenseScopeParam = $this->params()->fromQuery('licensescope', 'dataset');
        //$jsonParam = $this->params()->fromQuery('jsonDoc', null);
        //$fileParam = $this->params()->fromQuery('filename', null);
        $resourceIdParam = $this->params()->fromQuery('resourceid', null);
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
                        'label' => 'Select and apply a license',
                        'target' => 'dataset-policies',
                        'params' => [
                            'action' => 'select',
                            'id' => $id
                        ]
                    ]
                ]
            ];

            // Get license scope selection and load license, assignee list and history accordingly.
            switch ($licenseScopeParam) {
                case "jsondoc":
                    $resourceList = $this->_policyRepository->getUserResourceList($dataset->uuid, $assigneeParam, 'document');
                    // If jsondoc scope has been selected and no resource yet selected, return the first resource and display that until further selection is made
                    if (is_null($resourceIdParam)) {
                        $resourceIdParam = $resourceList[0];
                    }
                    $license = $this->_policyRepository->getResourceUserLicense($dataset->uuid, $assigneeParam, 'document', $resourceIdParam);
                    $assigneeList = $this->_policyRepository->getResourceLicenseUserList($dataset->uuid, 'document');
                    $history = $this->_policyRepository->getResourceUserLicenseHistory($dataset->uuid, $assigneeParam, 'document', $resourceIdParam);
                    break;
                case "file":
                    $resourceList = $this->_policyRepository->getUserResourceList($dataset->uuid, $assigneeParam, 'file');
                    // If file scope has been selected and no resource yet selected, return the first resource and display that until further selection is made
                    if (is_null($resourceIdParam)) {
                        $resourceIdParam = $resourceList[0];
                    }
                    $license = $this->_policyRepository->getResourceUserLicense($dataset->uuid, $assigneeParam, 'file', $resourceIdParam);
                    $assigneeList = $this->_policyRepository->getResourceLicenseUserList($dataset->uuid, 'file');
                    $history = $this->_policyRepository->getResourceUserLicenseHistory($dataset->uuid, $assigneeParam, 'file', $resourceIdParam);
                    break;
                default:
                    $license = $this->_policyRepository->getDatasetUserLicense($dataset->uuid, $assigneeParam);
                    $assigneeList = $this->_policyRepository->getDatasetLicenseUserList($dataset->uuid);
                    $resourceList = [];
                    $history = $this->_policyRepository->getDatasetUserLicenseHistory($dataset->uuid, $assigneeParam);
            }

            return new ViewModel([
                'messages' => $messages,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'license' => $license,
                'history' => $history,
                'assigneeList' => $assigneeList,
                'assignee' => $assigneeParam,
                'licenseScope' => $licenseScopeParam,
                'resourceList' => $resourceList,
                'resourceId' => $resourceIdParam,
            ]);
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    private function getDatasetLicenseFromLicenseMetadata($data, $assignee) {
        $activeLicenses = $data['dataset']['active'];
        foreach ($activeLicenses as $license) {
            if ($license['odrl:assignee'] == $assignee) {
                return ($license);
            }
        }
        return $activeLicenses[0];
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
            $licenses = json_decode($this->_policyRepository->getLicenses());
            $customLicenses = json_decode($this->_policyRepository->getCustomLicenses($dataset->uuid), True);

            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'licenses' => $licenses,
                'customLicenses' => $customLicenses,
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

            $allPolicies = $this->_policyRepository->getAllPolicies();

            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'allPolicies' => $allPolicies,
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

    public function createconfirmAction() {
        // Check credentials
        // Build license from custom policy data
        // Store in custom library for future use

        $user_id = $this->currentUser()->getId();
        $user_email = $this->currentUser()->getEmail();
        $id = (int) $this->params()->fromRoute('id', 0);
        $assigneeEmail = $this->params()->fromQuery('assigneeEmail', null);

        $dataset = $this->_dataset_repository->findDataset($id);
        //$permissions = $this->_repository->findDatasetPermissions($id);
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

        if ($can_view && $can_edit) {
            if($this->getRequest()->isPost()) {
                $data = $this->params()->fromPost();

            }
            $policies = $this->processCustomLicenseFormData($data);
            $newLicense = $this->saveCustomLicense($data, $policies, $dataset->uuid, $id, $user_email);

            //return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
            return new ViewModel([
                'messages' => $messages,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                //'token' => $token,
                'assigneeEmail' => $assigneeEmail,
                'data' => $data,
                'policies' => $policies,
                'newLicense' => $newLicense,
                'complete' => true
            ]);

        }
        else {
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    private function processCustomLicenseFormData($formData) {
        $dataObject = [
            'licenseTitle' => $formData['licenseTitle'],
            'licenseText' => $formData['licenseText'],
            'policies' => $formData['policies']
        ];
        $policies = json_decode($formData['policies'], true);
        return $policies;
    }

    public function customAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_dataset_repository->findDataset($id);
        $licenseId = $this->params()->fromQuery('license', null);
        $licenseBody = json_decode($this->_policyRepository->getCustomLicenses($dataset->uuid, $licenseId),True)[0];
        return new JsonModel([$licenseBody]);
    }

    public function libraryAction() {
        $uid = $this->params()->fromRoute('uid', null);
        $search = null;
        if (!is_null($uid)) {
            $search = [
                '_id' => $uid
            ];
        }
        $licenses = json_decode($this->_policyRepository->getLicenses($search));
        return new JsonModel($licenses);
    }

    public function applyAction() {
        $user_id = $this->currentUser()->getId();
        $user_email = $this->currentUser()->getEmail();
        $id = (int) $this->params()->fromRoute('id', 0);
        $token = $this->params()->fromQuery('token', null);
        $license = $this->params()->fromQuery('license', null);
        $userScope = $this->params()->fromQuery('userScope', null);
        $licenseScope = $this->params()->fromQuery('licenseScope', null);
        $jsonDoc = $this->params()->fromQuery('jsondoc', null);
        $filename = $this->params()->fromQuery('filename', null);
        $assigneeEmail = $this->params()->fromQuery('assigneeEmail', null);

        $custom = false;
        if (substr($license, 0, 8) == '-custom-'){
            $license = substr($license, 8);
            $custom = true;
        }

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

            // If licenseScope is resource-specific, check the supplied docId/filename is valid
            switch ($licenseScope) {
                case 'json':
                    // Check json doc ID exists
                    if (is_null($jsonDoc) || ($jsonDoc == ""))
                    {
                        $this->flashMessenger()->addMessage('Error: no JSON document ID specified');
                        return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
                    }
                    if (!$this->checkLicenseScope($dataset->uuid, $licenseScope, $jsonDoc)) {
                        $this->flashMessenger()->addMessage('Error: unable to locate JSON document ID');
                        return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
                    }
                    $resourceId = $jsonDoc;
                    break;
                case 'file':
                    if (is_null($filename) || ($filename == ""))
                    {
                        $this->flashMessenger()->addMessage('Error: No filename specified');
                        return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
                    }
                    // Check filename exists
                    if (!$this->checkLicenseScope($dataset->uuid, $licenseScope, $filename)) {
                        $this->flashMessenger()->addMessage('Error: unable to locate file');
                        return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id'=>$dataset->id]);
                    }
                    $resourceId = $filename;
                    break;
                default:
                    $licenseScope = 'dataset';
                    $resourceId = null;
            }

            // If applying license to a single user, check email address is a valid user before getting to the token stage
            if ($userScope == 'namedUser') {
                $assigneeUserId =  $this->userIdFromEmail($assigneeEmail);
                if ($assigneeUserId == 0) {
                    $this->flashMessenger()->addMessage('No such user - '.$assigneeEmail);
                    return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id' => $dataset->id]);
                }
                else {
                    // set assignee
                    // $assigneeEmail already set, no need to worry
                }
            }
            else {
                $assigneeEmail = "all";
            }

            if (!is_null($token)) {
                $container = new Container('apply_license');
                $valid_token = ($container->apply_token == $token);
                if ($valid_token) {  // Apply license here...
                    // "-custom-" won't be included in the license name after the confirm/token page is submitted, but
                    // it will be included as a URL param, so get it from there instead.
                    $retrieveCustom = $this->params()->fromQuery('custom', false);
                    //Retrieve license
                    $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $dataset->uuid), True);
                    $metadata = [];
                    if (count($metadataResponse) >= 1) {
                        $metadata = $metadataResponse[0];
                    }
                    else {
                        //the metadata has no '_id', so create it
                        $metadata['_id'] = $dataset->uuid;
                    }
                    $newMetadata = $this->addLicenseToMetadata($dataset->uuid, $license, $retrieveCustom, $user_email, $assigneeEmail, $licenseScope, $resourceId, $metadata);
                    $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($newMetadata), $metadata['_id']);
                    switch ($licenseScope) {
                        case 'json':
                            $scopeText = "JSON document: " . $resourceId;
                            break;
                        case 'file':
                            $scopeText = "file: " . $resourceId;
                            break;
                        default:
                            $scopeText = "dataset";
                    }
                    $this->flashMessenger()->addMessage('The license has been applied to the '. $scopeText);
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
                    'license' => $license,
                    'licenseScope' => $licenseScope,
                    'jsonDoc' => $jsonDoc,
                    'filename' => $filename,
                    'userScope' => $userScope,
                    'assigneeEmail' => $assigneeEmail,
                    'custom' => $custom
                ]);
            }
        }
        else{
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }


    // If the license scope is an individual jsondoc or file (rather than the entire dataset), check that
    // the specified jsondoc or file actually exists within the dataset
    private function checkLicenseScope($datasetUuid, $licenseScope, $resourceId) {
        switch ($licenseScope) {
            case "json":
                $response = json_decode($this->_repository->getDocument($datasetUuid, $resourceId), True);
                if (count($response) >= 1) {
                    return true;
                }
                break;
            case "file":
                $response = $this->_fileRepository->findDatasetFiles($datasetUuid, null);
                foreach ($response as $fileItem) {
                    if ($fileItem['filenameOriginal'] == $resourceId) {
                        return true;
                    }
                }
                break;
            default:
                return false;
        }
        return false;
    }



    // TODO - This function isnt active yet.
    public function deleteAction() {
        $user_id = $this->currentUser()->getId();
        $user_email = $this->currentUser()->getEmail();
        $id = (int) $this->params()->fromRoute('id', 0);
        $token = $this->params()->fromQuery('token', null);
        $license = $this->params()->fromQuery('license', null);
        $userScope = $this->params()->fromQuery('userScope', null);
        $licenseScope = $this->params()->fromQuery('licenseScope', null);
        $assigneeEmail = $this->params()->fromQuery('assigneeEmail', null);

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
                return $this->redirect()->toRoute('dataset-policies', ['action' => 'index', 'id' => $dataset->id]);
            }
            // If applying license to a single user, check email address is a valid user before getting to the token stage
            if ($userScope == 'namedUser') {
                $assigneeUserId =  $this->userIdFromEmail($assigneeEmail);
                if ($assigneeUserId == 0) {
                    $this->flashMessenger()->addMessage('No such user - '.$assigneeEmail);
                    return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id' => $dataset->id]);
                }
                else {
                    // set assignee
                    // $assigneeEmail already set, no need to worry
                }
            }
            else {
                $assigneeEmail = "all";
            }
            if (!is_null($token)) {
                $container = new Container('delete_license');
                $valid_token = ($container->delete_token == $token);
                if ($valid_token) {  // Delete license here...
                    //Retrieve license
                    $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $dataset->uuid), True);
                    $metadata = [];
                    if (count($metadataResponse) < 1) {
                        $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($metadata), $metadata['_id']);
                        $this->flashMessenger()->addMessage('Error: Dataset metadata does not exist');
                    }
                    else {
                        $metadata = $metadataResponse[0];
                    }
                    // TODO - Delete license here
                    //$this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($newMetadata), $metadata['_id']);
                    $this->flashMessenger()->addMessage('The license has been applied to the dataset');
                }
                else {
                    $this->flashMessenger()->addMessage('Error: Invalid token. The license was not deleted.');
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
                    'license' => $license,
                    'userScope' => $userScope,
                    'assigneeEmail' => $assigneeEmail,
                ]);
            }
        }
        else {
            $this->flashMessenger()->addMessage('You do not have manage rights on this dataset');
            return $this->redirect()->toRoute('dataset', ['action'=>'details', 'id'=>$dataset->id]);
        }
    }

    // ******************************************************************************************
    // ******************************************************************************************

    private function saveCustomLicense($data, $policies, $datasetUuid, $datasetId, $user_email) {
        $contractedTitle = str_replace(' ', '', $data['licenseTitle']);

        $licenseBody = [];
        $licenseBody['@context'] = ["https://spice.kmi.open.ac.uk/context/policyLayer.jsonld", "http://www.w3.org/ns/odrl.jsonld"];
        $licenseBody['@type'] = 'odrl:policy';
        $licenseBody['s'] = $datasetUuid . '-' . $contractedTitle;
        $licenseBody['odrl:uid'] = $datasetUuid . '-' . $contractedTitle;
        $licenseBody['odrl:profile'] = "";
        $licenseBody['odrl:target'] = $datasetUuid;
        $licenseBody['odrl:assigner'] = $user_email;
        $licenseBody['odrl:assignee'] = "";
        $licenseBody['schema:title'] = $data['licenseTitle'];
        $licenseBody['schema:text'] = $data['licenseText'];
        $licenseBody['active'] = false;
        $licenseBody['custom']= true;
        $licenseBody['created-time'] = time();
        $licenseBody['schema:validFrom'] = "";
        $licenseBody['schema:validTo'] = "";
        $licenseBody['odrl:permission'] = [];
        $licenseBody['odrl:obligation'] = [];
        $licenseBody['odrl:prohibition'] = [];

        foreach ($policies['permissions'] as $item) {
            $licenseBody['odrl:permission'][] = $item;
        }
        foreach ($policies['obligations'] as $item) {
            $licenseBody['odrl:obligation'][] = $item;
        }
        foreach ($policies['prohibitions'] as $item) {
            $licenseBody['odrl:prohibition'][] = $item;
        }

        $emptyPolicy = [
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
            ],
            'custom' => []
        ];

        //Check that license title doesn't already exist...
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) >= 1) {
            if (isset($metadataResponse[0]['policy'])) {
                foreach($metadataResponse[0]['policy']['custom'] as $item) {
                    if($item['schema:title'] == $licenseBody['schema:title']) {
                        // this already exists
                        $this->flashMessenger()->addMessage('Error: this custom license title already exists.');
                        return $this->redirect()->toRoute('dataset-policies', ['action'=>'index', 'id' => $datasetId]);                    }
                }
            }
            else { // no policy data yet within the dataset metadata
                $metadataResponse[0]['policy'] = $emptyPolicy;
            }
        }
        else { // no metadata for this dataset
            $metadataResponse[0] = [
                '_id' => $datasetUuid,
                'policy' => $emptyPolicy
            ];
        }

        //Push license to dataset metadata...
        $metadataResponse[0]['policy']['custom'][] = $licenseBody;
        $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($metadataResponse[0]), $datasetUuid);

        //return license ODRL source...
        return $licenseBody;
    }

    // ******************************************************************************************
    // ******************************************************************************************

    private function addLicenseToMetadata($datasetUuid, $licenseId, $custom, $assigner, $assignee, $licenseScope, $resourceId, $metadata) {
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
                    ],
                    'custom' => []
                ];
        }
        switch ($licenseScope) {
            case 'json':
                $scope = 'document';
                break;
            case 'file':
                $scope = 'file';
                break;
            default:
                $scope = 'dataset';
        }

        $licenseReplaced = False;
        $nowTime = time();
        $keysToRemove = [];
        foreach ($metadata['policy'][$scope]['active'] as $key => $item) {
            if (isset($item)) {
                if (!isset($item['resourceID'])) {
                    $item['resourceID'] = null;
                }
                if (isset($item['odrl:assignee']) && isset($item['active'])) {
                    if ($item['odrl:assignee'] == $assignee && $item['active'] && $item['resourceID'] == $resourceId) {
                        $item['active'] = False;
                        $item['modified-time'] = $nowTime;
                        $item['schema:validUntil'] = $nowTime;
                        array_unshift($metadata['policy'][$scope]['inactive'], $item);
                        array_unshift($keysToRemove, $key);
                        $licenseReplaced = True;
                    }
                }
            }
        }
        foreach ($keysToRemove as $removeKey) {
            array_splice($metadata['policy'][$scope]['active'], $removeKey, 1);
        }
        $search = [
            '_id' => $licenseId
        ];
        if (!$custom) {
            $licenseBody = json_decode($this->_policyRepository->getLicenses($search, False),True)[0];
        }
        else {
            $licenseBody = json_decode($this->_policyRepository->getCustomLicenses($datasetUuid, $licenseId),True)[0];
        }
        $licenseBody['odrl:assignee'] = $assignee;
        $licenseBody['odrl:assigner'] = $assigner;
        $licenseBody['odrl:target'] = $datasetUuid; // FIXME - dataset URI here
        $licenseBody['resourceID'] = $resourceId;
        $licenseBody['active'] = True;
        $licenseBody['created-time'] = $nowTime;
        $licenseBody['schema:validFrom'] = $nowTime;
        $licenseBody['schema:validUntil'] = 7500000000; // 185 years away
        array_unshift($metadata['policy'][$scope]['active'], $licenseBody);

        return $metadata;
    }
}