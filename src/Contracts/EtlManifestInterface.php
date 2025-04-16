<?php

namespace Laravelplus\EtlManifesto\Contracts;

interface EtlManifestInterface
{
    /**
     * Load and parse the manifest file
     */
    public function loadManifest(string $path): self;

    /**
     * Process the loaded manifest
     */
    public function process(): array;

    /**
     * Get the current manifest data
     */
    public function getManifest(): array;
}
