<?php
/**
 * laravel
 *
 * @author    Jérémy GAULIN <jeremy@bnb.re>
 * @copyright 2017 - B&B Web Expertise
 */

namespace Bnb\BootstrapForm;

use Collective\Html\FormBuilder as BaseFormBuilder;

class FormBuilder extends BaseFormBuilder
{

    /**
     * Default values that prevail over models one (lower priority than old and request)
     *
     * @var array
     */
    protected $defaultValues = [];


    /**
     * FormBuilder constructor.
     */
    public function __construct(BaseFormBuilder $form)
    {
        parent::__construct($form->html, $form->url, $form->view, $form->csrfToken, $form->request);
    }


    public function getValueAttribute($name, $value = null)
    {
        $session = $this->getSessionStore();

        if ($value === null && isset($this->defaultValues[$name])) {
            $value = $this->defaultValues[$name];
        }

        if ($session === null || $session->getName() === 'null') {
            return $value ?? $this->getModelValueAttribute($name);
        }

        return parent::getValueAttribute($name, $value);
    }


    /**
     * Get the model value that should be assigned to the field.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getModelValueAttribute($name)
    {
        $key = $this->transformKey($name);

        if ((is_string($this->model) || is_object($this->model)) && method_exists($this->model, 'getFormValue')) {
            return $this->model->getFormValue($key);
        }

        $data = data_get($this->model, $key);

        if ($data instanceof \BackedEnum) {
            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        return $data;
    }


    /**
     * Set the default values that prevail over model ones (useful for filter)
     */
    public function setDefaultValues(array $values): void
    {
        $this->defaultValues = $values;
    }
}
