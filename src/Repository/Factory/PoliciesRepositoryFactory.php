<?php

namespace MKDF\Policies\Repository\Factory;

use Interop\Container\ContainerInterface;
use MKDF\Policies\Repository\PoliciesRepository;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PoliciesRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        //$repository = $container->get(MKDFKeysRepositoryInterface::class);
        $streamApi_repository = $container->get(MKDFStreamRepositoryInterface::class);
        return new PoliciesRepository($streamApi_repository,$config);
    }
}
