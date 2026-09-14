<?php

namespace madong\workflow\config;

use madong\workflow\container\ContainerInterface;

class EngineConfig
{
    public function __construct(
        public readonly array $services = [],
        public readonly array $aspects = [],
        public readonly array $modules = [],
        public readonly array $extensions = [],
        public readonly bool  $debug = false,
        public readonly ?ContainerInterface $container = null,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            return new self();
        }

        $config = require $path;
        return new self(
            services:   $config['services'] ?? [],
            aspects:    $config['aspects'] ?? [],
            modules:    $config['modules'] ?? [],
            extensions: $config['extension_parameters'] ?? $config['extensions'] ?? [],
            debug:      $config['debug'] ?? false,
        );
    }

    public static function fromArray(array $config): self
    {
        return new self(
            services:   $config['services'] ?? [],
            aspects:    $config['aspects'] ?? [],
            modules:    $config['modules'] ?? [],
            extensions: $config['extension_parameters'] ?? $config['extensions'] ?? [],
            debug:      $config['debug'] ?? false,
        );
    }
}
