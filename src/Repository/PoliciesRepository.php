<?php

namespace MKDF\Policies\Repository;

use APIF\Sparql\Repository\GraphRepositoryInterface;
use MKDF\Stream\Repository\Factory\MKDFStreamRepositoryFactory;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;

class PoliciesRepository implements PoliciesRepositoryInterface
{
    private $_config;
    private $_datasetAPIUrl;
    private $_repository;

    public function __construct(MKDFStreamRepositoryInterface $repository, $config)
    {
        $this->_config = $config;
        $this->_datasetAPIUrl = $this->_config['mkdf-stream']['server-url']."/object/".$this->_config['mkdf-policies']['policies-dataset'];
        $this->_repository = $repository;
    }

    private function _checkDateValid($license, $date)
    {
        if (($license['schema:validFrom'] < $date) && ($license['schema:validUntil'] > $date)) {
            return true;
        }
        else {
            return false;
        }
    }

    // Return a dictionary/assoc-array to look up license uid => title
    public function getLocalLicenseTitles($datasetUuid) {
        $licenseTitles = [];
        //$query = [];
        $queryJSON = '{}';
        $fields = 'policy.document.odrl:uid,policy.document.schema:title,policy.file.odrl:uid,policy.file.schema:title,policy.dataset.odrl:uid,policy.dataset.schema:title';
        $metadataResponse = json_decode($this->_repository->browseDocuments($this->_config['mkdf-stream']['dataset-metadata'], $queryJSON, $fields), True)['results'];
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'])){
            foreach ($metadata['policy']['dataset'] as $licenseItem) {
                $licenseTitles[$licenseItem['odrl:uid']] = $licenseItem['schema:title'];
            }
        }
        return $licenseTitles;
    }

    public function getDatasetUserLicense($datasetUuid, $assignee, $active = true){
        $licenses = [];
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'])){
            foreach ($metadata['policy']['dataset'] as $licenseItem) {
                if (($licenseItem['odrl:assignee'] == $assignee) && ($this->_checkDateValid($licenseItem, time()) == $active)) {
                    array_push($licenses, $licenseItem);
                }
            }
        }
        return $licenses;
    }

    public function getResourceUserLicense($datasetUuid, $assignee, $resourceType, $resourceId, $active = true) {
        $licenses = [];
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'])){
            foreach ($metadata['policy'][$resourceType] as $licenseItem) {
                if ($licenseItem['odrl:assignee'] == $assignee && $licenseItem['resourceID'] == $resourceId && ($this->_checkDateValid($licenseItem, time()) == $active)) {
                    array_push($licenses, $licenseItem);
                }
            }
        }
        return $licenses;
    }

    public function getDatasetLicenseUserList($datasetUuid) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        $userList = [];
        if (isset($metadata['policy'])){
            // Loop through all the active policies
            foreach ($metadata['policy']['dataset'] as $license) {
                if (!in_array($license['odrl:assignee'], $userList)) {
                    array_push($userList, $license['odrl:assignee']);
                }
            }
        }
        return $userList;
    }

    public function getResourceLicenseUserList($datasetUuid, $resourceType) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        $userList = [];
        if (isset($metadata['policy'])){
            // Loop through all the active policies
            foreach ($metadata['policy'][$resourceType] as $license) {
                if (!in_array($license['odrl:assignee'], $userList)) {
                    array_push($userList, $license['odrl:assignee']);
                }
            }
        }
        return $userList;
    }

    public function getUserResourceList($datasetUuid, $assignee, $resourceType, $onlyActive = false) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        $resourceList = [];
        if (isset($metadata['policy'][$resourceType])){
            foreach ($metadata['policy'][$resourceType] as $license) {
                if ($license['odrl:assignee'] == $assignee) {
                    if (!in_array($license['resourceID'], $resourceList))
                    {
                        array_push($resourceList, $license['resourceID']);
                    }
                }
            }
        }
        return $resourceList;
    }

    public function getDatasetUserLicenseHistory($datasetUuid, $assignee) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        $history = [];
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy']['dataset']['inactive'])){
            foreach ($metadata['policy']['dataset']['inactive'] as $license) {
                if ($license['odrl:assignee'] == $assignee) {
                    array_push($history, $license);
                }
            }
        }
        return $history;
    }

    public function getResourceUserLicenseHistory($datasetUuid, $assignee, $resourceType, $resourceId) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        $history = [];
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'][$resourceType]['inactive'])){
            foreach ($metadata['policy'][$resourceType]['inactive'] as $license) {
                if ($license['odrl:assignee'] == $assignee && $license['resourceID'] == $resourceId) {
                    array_push($history, $license);
                }
            }
        }
        return $history;
    }

    public function getLicenses($search = null, $creationCheck = True) {
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

    public function getCustomLicenses($datasetUuid, $licenseId = null) {
        $metadataDataset = $this->_config['mkdf-stream']['dataset-metadata'];

        $metadataResponse = json_decode($this->_repository->getDocument($metadataDataset, $datasetUuid), True);
        if (count($metadataResponse) >= 1) {
            if (is_null($licenseId)){
                return json_encode($metadataResponse[0]['policy']['custom'], JSON_UNESCAPED_SLASHES);
            }
            else {
                //var_dump($metadataResponse[0]['policy']['custom']);
                // Look through the custom policies and only return a single one...
                foreach ($metadataResponse[0]['policy']['custom'] as $singleItem) {
                    $contractedTitle = str_replace(' ', '', $licenseId);
                    $uid = $datasetUuid . '-' . $contractedTitle;
                    if ($singleItem['odrl:uid'] == $uid) {
                        //return as a single item array for consistency with return format of function that returns standard licenses
                        return json_encode([$singleItem], JSON_UNESCAPED_SLASHES);
                    }
                }
            }

        }

        return null;
    }

    public function getAllPolicies() {
        $licenses = json_decode($this->getLicenses(),true);
        $permissionList= [];
        $obligationList = [];
        $prohibitionList = [];
        // FIXME - Remove duplicates...
        foreach ($licenses as $license) {
            foreach ($license['odrl:permission'] as $permission) {
                if (!in_array($permission['action'][0], $permissionList))
                {
                    array_push($permissionList, $permission['action'][0]);
                }
            }
            foreach ($license['odrl:obligation'] as $obligation) {
                if (!in_array($obligation['action'][0], $obligationList))
                {
                    array_push($obligationList, $obligation['action'][0]);
                }
            }
            foreach ($license['odrl:prohibition'] as $prohibition) {
                if (!in_array($prohibition['action'][0], $prohibitionList))
                {
                    array_push($prohibitionList, $prohibition['action'][0]);
                }
            }
        }
        $allPolicies = [
            'permissions' => $permissionList,
            'obligations' => $obligationList,
            'prohibitions' => $prohibitionList
        ];
        return $allPolicies;
    }

    public function assignLicenseToKeyAccess($datasetUuid, $keyUuid, $userEmail, $licenseId) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        $metadata = [];
        if (count($metadataResponse) >= 1) {
            $metadata = $metadataResponse[0];
        }
        else {
            //the metadata has no '_id', so create it
            $metadata['_id'] = $datasetUuid;
        }
        if (!isset($metadata['policy'])) {
            $metadata['policy'] =
                [
                    'dataset' => [],
                    'document' => [],
                    'file' => [],
                    'custom' => [],
                    'keys' => []
                ];
        }
        if (!isset($metadata['policy']['keys'])) {
            $metadata['policy']['keys'] = [];
        }
        $nowTime = time();
        $newEntry = [
            'timestamp' => $nowTime,
            'key' => $keyUuid,
            'user' => $userEmail,
            'license' => $licenseId,
            'active' => true
        ];
        array_unshift($metadata['policy']['keys'],$newEntry);
        //$newMetadata = $this->addLicenseToMetadata($dataset->uuid, $license, $retrieveCustom, $user_email, $assigneeEmail, $licenseScope, $resourceId, $validFrom, $validUntil, $metadata);
        $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($metadata), $metadata['_id']);
        return true;
    }

    public function removeLicenseKeyAssoc ($datasetUuid, $keyUuid) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        $found = false;
        if (count($metadataResponse) >= 1) {
            $metadata = $metadataResponse[0];
            if (isset($metadata['policy']['keys'])){
                foreach ($metadata['policy']['keys'] as $i=>$licenseKeyAssoc){
                    if ($licenseKeyAssoc['key'] == $keyUuid) {
                        // We found the association at $i, remove that item
                        $found = true;
                        array_splice($metadata['policy']['keys'], $i, 1);
                        // Now, rewrite the metadata back to the DB...
                        $this->_repository->updateDocument($this->_config['mkdf-stream']['dataset-metadata'],json_encode($metadata), $metadata['_id']);
                    }
                }
            }
        }

        return $found;
    }

    public function getLicenseKeyAssoc ($datasetUuid, $keyUuid) {
        $license = null;
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) >= 1) {
            $metadata = $metadataResponse[0];
            if (isset($metadata['policy']['keys'])){
                foreach ($metadata['policy']['keys'] as $licenseKeyAssoc){
                    if ($licenseKeyAssoc['key'] == $keyUuid) {
                        $license = $licenseKeyAssoc['license']; //FIXME - get license details here (for proper formatted title/label)
                        //var_dump($licenseKeyAssoc);
                    }
                }
            }
        }
        return $license;
    }

    public function getUserLicenseKeyAssocs($datasetUuid, $user = 'all') {
        $licenseAssocs = [];
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) >= 1) {
            $metadata = $metadataResponse[0];
            if (isset($metadata['policy']['keys'])){
                if ($user != 'all') {
                    foreach ($metadata['policy']['keys'] as $licenseKeyAssoc){
                        if ($licenseKeyAssoc['user'] == $user) {
                            array_unshift($licenseAssocs, $licenseKeyAssoc);
                        }
                    }
                }
                else {
                    $licenseAssocs = $metadata['policy']['keys'];
                }
            }
        }
        return $licenseAssocs;
    }

    public function createNewLicenseRequest($obj, $creationCheck = True) {
        $requestsDataset = $this->_config['mkdf-policies']['policies-requests-dataset'];
        $requestsKey = $this->_config['mkdf-policies']['policies-key'];

        if ($creationCheck) {
            $requestsDatasetExists = $this->_repository->getStreamExists($requestsDataset);
            if (!$requestsDatasetExists) {
                $this->_repository->createDataset($requestsDataset,$requestsKey);
            }
        }

        $returnObj = $this->_repository->pushDocument($this->_config['mkdf-policies']['policies-requests-dataset'], json_encode($obj));
        //var_dump($returnObj);
    }

    public function updateLicenseRequest($requestId, $request) {
        $requestsDataset = $this->_config['mkdf-policies']['policies-requests-dataset'];
        $requestsKey = $this->_config['mkdf-policies']['policies-key'];


        $this->_repository->updateDocument($requestsDataset,json_encode($request), $requestId);
    }

    public function getDatasetLicenseRequests ($datasetUuid, $user = null, $creationCheck = True) {
        $requestsDataset = $this->_config['mkdf-policies']['policies-requests-dataset'];
        $requestsKey = $this->_config['mkdf-policies']['policies-key'];

        if ($creationCheck) {
            $requestsDatasetExists = $this->_repository->getStreamExists($requestsDataset);
            if (!$requestsDatasetExists) {
                $this->_repository->createDataset($requestsDataset,$requestsKey);
            }
        }

        if (is_null($user)) {
            $query = [
                'dataset' => $datasetUuid
            ];
        }
        else {
            $query = [
                'dataset' => $datasetUuid,
                'user' => $user
            ];
        }
        $key = $this->_config['mkdf-policies']['policies-key'];
        $queryJSON = json_encode($query);
        //print($queryJSON);
        return json_decode($this->_repository->getDocuments($this->_config['mkdf-policies']['policies-requests-dataset'],1000,$key,$queryJSON), True);
    }

    public function getSingleDatasetLicenseRequest ($datasetUuid, $requestId) {
        $requestsDataset = $this->_config['mkdf-policies']['policies-requests-dataset'];
        $requestsKey = $this->_config['mkdf-policies']['policies-key'];

        $query = [
            'dataset' => $datasetUuid,
            '_id' => $requestId
        ];
        $queryJSON = json_encode($query);
        return json_decode($this->_repository->getDocuments($requestsDataset,1,$requestsKey,$queryJSON), True)[0];
    }
}