<?php

namespace Bnb\BootstrapForm\Facades;

use Illuminate\Support\Facades\Facade;

class BootstrapForm extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bootstrap_form';
    }
}
