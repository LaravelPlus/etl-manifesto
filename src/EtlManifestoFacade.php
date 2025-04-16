<?php

namespace Laravelplus\EtlManifesto;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laravelplus\EtlManifesto\Skeleton\SkeletonClass
 */
class EtlManifestoFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'etl-manifesto';
    }
}
