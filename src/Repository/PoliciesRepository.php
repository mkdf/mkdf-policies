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

    public function getAllPolicies() {
        $licenses = $this->getLicenses();
        $permissionList= [];
        $obligationList = [];
        $prohibitionList = [];
        // TODO - Loop here...
        $allPolicies = [
            'permissions' => $permissionList,
            'obligations' => $obligationList,
            'prohibitions' => $prohibitionList
        ];
        return $allPolicies;
    }
}