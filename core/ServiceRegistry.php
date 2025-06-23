<?php

namespace App\Services;

class ServiceRegistry
{
    private array $services = [];
    private array $toolToServiceMap = [];

    public function __construct(array $serviceConfigs, ?array $google_access_token = null, ?\Psr\Log\LoggerInterface $logger = null)
    {
        foreach ($serviceConfigs as $serviceName => $config) {
            $class = $config['class'];
            if (class_exists($class) && is_subclass_of($class, Service::class)) {
                $service = new $class($config, $google_access_token, $logger);
                $this->register($service);
            }
        }
    }

    public function register(Service $service): void
    {
        $serviceName = $service->getName();
        $this->services[$serviceName] = $service;

        foreach ($service->getAvailableTools() as $toolName) {
            $this->toolToServiceMap[$toolName] = $serviceName;
        }
    }

    public function getServiceForTool(string $toolName): ?Service
    {
        $serviceName = $this->toolToServiceMap[$toolName] ?? null;
        if ($serviceName) {
            return $this->services[$serviceName] ?? null;
        }
        return null;
    }

    public function getService(string $name): ?Service
    {
        return $this->services[$name] ?? null;
    }

    public function getServices(): array
    {
        return $this->services;
    }

    public function getToolList(): array
    {
        return array_keys($this->toolToServiceMap);
    }

    public function getToolDefinitions(): array
    {
        $definitions = [];
        foreach ($this->services as $service) {
            $definitions = array_merge($definitions, $service->getToolDefinitions());
        }
        return $definitions;
    }

    public function executeTool(string $toolName, array $arguments): array
    {
        $service = $this->getServiceForTool($toolName);
        if ($service) {
            return $service->executeTool($toolName, $arguments);
        }
        throw new \Exception("Tool '{$toolName}' not found in any registered service.");
    }
} 