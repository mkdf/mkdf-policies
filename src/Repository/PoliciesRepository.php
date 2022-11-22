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

    public function getDatasetUserLicense($datasetUuid, $assignee){
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'])){
            foreach ($metadata['policy']['dataset']['active'] as $licenseItem) {
                if ($licenseItem['odrl:assignee'] == $assignee) {
                    return $licenseItem;
                }
            }
        }
        return null;
    }

    public function getResourceUserLicense($datasetUuid, $assignee, $resourceType, $resourceId) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        if (isset($metadata['policy'])){
            foreach ($metadata['policy'][$resourceType]['active'] as $licenseItem) {
                if ($licenseItem['odrl:assignee'] == $assignee && $licenseItem['resourceID'] == $resourceId) {
                    return $licenseItem;
                }
            }
        }
        return null;
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
            foreach ($metadata['policy']['dataset']['active'] as $license) {
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
            foreach ($metadata['policy'][$resourceType]['active'] as $license) {
                if (!in_array($license['odrl:assignee'], $userList)) {
                    array_push($userList, $license['odrl:assignee']);
                }
            }
        }
        return $userList;
    }

    public function getUserResourceList($datasetUuid, $assignee, $resourceType) {
        $metadataResponse = json_decode($this->_repository->getDocument($this->_config['mkdf-stream']['dataset-metadata'], $datasetUuid), True);
        if (count($metadataResponse) === 0) {
            // list is empty.
            $metadata = [];
        }
        else {
            $metadata = $metadataResponse[0];
        }
        $resourceList = [];
        if (isset($metadata['policy'][$resourceType]['active'])){
            foreach ($metadata['policy'][$resourceType]['active'] as $license) {
                if ($license['odrl:assignee'] == $assignee) {
                    array_push($resourceList, $license['resourceID']);
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
}