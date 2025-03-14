<?php

namespace Bnb\BootstrapForm;

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Bnb\BootstrapForm\FormBuilder as BnbFormBuilder;

class BootstrapForm
{

    /**
     * Illuminate HtmlBuilder instance.
     *
     * @var HtmlBuilder
     */
    protected $html;

    /**
     * Custom FormBuilder instance.
     *
     * @var BnbFormBuilder
     */
    protected $form;

    /**
     * Illuminate Repository instance.
     *
     * @var Repository
     */
    protected $config;

    /**
     * Bootstrap form type class.
     *
     * @var string
     */
    protected $type;

    /**
     * Bootstrap form left column class.
     *
     * @var string
     */
    protected $leftColumnClass;

    /**
     * Bootstrap form left column offset class.
     *
     * @var string
     */
    protected $leftColumnOffsetClass;

    /**
     * Bootstrap form right column class.
     *
     * @var string
     */
    protected $rightColumnClass;

    /**
     * The errorbag that is used for validation (multiple forms)
     *
     * @var string
     */
    protected $errorBag = 'default';

    /**
     * Suffix to add to the label string when a field is required
     *
     * @var string
     */
    protected $labelRequiredMark;

    /**
     * Suffix of .{valid|invalid}-[mode] class added to (in)valid notes
     *
     * @var string
     */
    protected $invalidMode;

    /**
     * CSS class to add to the form group when a field is required
     *
     * @var string
     */
    protected $groupRequiredClass;

    /**
     * The request is a post back
     * @var bool
     */
    protected $isPostBack;

    /**
     * @var array
     */
    private $fieldsError;

    /**
     * @var Session|null
     */
    private $previousSessionStore;


    /**
     * Construct the class.
     */
    public function __construct(HtmlBuilder $html, FormBuilder $form, Config $config)
    {
        $this->html = $html;
        $this->form = $form;
        $this->config = $config;
        $this->isPostBack = $this->form->getSessionStore()->has('errors');
    }


    /**
     * Open a form while passing a model and the routes for storing or updating
     * the model. This will set the correct route along with the correct
     * method.
     */
    public function open(array $options = []): string
    {
        $this->fieldsError = [];

        // Set the HTML5 role.
        $options['role'] = 'form';

        // Set the class for the form type.
        if ( ! array_key_exists('class', $options)) {
            $options['class'] = $this->getType();
        }

        if (array_key_exists('left_column_class', $options)) {
            $this->setLeftColumnClass($options['left_column_class']);
        }

        if (array_key_exists('left_column_offset_class', $options)) {
            $this->setLeftColumnOffsetClass($options['left_column_offset_class']);
        }

        if (array_key_exists('right_column_class', $options)) {
            $this->setRightColumnClass($options['right_column_class']);
        }

        if (array_key_exists('label_required_mark', $options)) {
            $this->setLabelRequiredMark($options['label_required_mark']);
        }

        if (array_key_exists('group_required_class', $options)) {
            $this->setLabelRequiredMark($options['group_required_class']);
        }

        if (array_key_exists('invalid_mode', $options)) {
            $this->setInvalidMode($options['invalid_mode'] === 'tooltip' ? 'tooltip' : 'feedback');
        }

        if (array_key_exists('null-session', $options)) {
            $this->previousSessionStore = $this->form->getSessionStore();

            $this->form->setSessionStore(new \Illuminate\Session\Store('null',
                new \Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler()));
        }

        Arr::forget($options, [
            'left_column_class',
            'left_column_offset_class',
            'right_column_class',
            'label_required_mark',
            'group_required_class',
        ]);

        if (array_key_exists('model', $options)) {
            return $this->model($options);
        }

        if (array_key_exists('errorbag', $options)) {
            $this->setErrorBag($options['errorbag']);
        }

        if (array_key_exists('values', $options)) {
            $this->form->setDefaultValues($options['values']);
            unset($options['values']);
        }

        return $this->form->open($options);
    }


    /**
     * Reset and close the form.
     */
    public function close(): string
    {
        $this->type = null;

        $this->leftColumnClass = $this->rightColumnClass = null;

        if ($this->previousSessionStore) {
            $this->form->setSessionStore($this->previousSessionStore);

            $this->previousSessionStore = null;
        }

        return $this->form->close();
    }


    /**
     * Open a form configured for model binding.
     */
    protected function model(array $options): string
    {
        $model = $options['model'];
        if (isset($options['url'])) {
            // If we're explicity passed a URL, we'll use that.
            Arr::forget($options, ['model', 'update', 'store']);
            $options['method'] = isset($options['method']) ? $options['method'] : 'GET';

            return $this->form->model($model, $options);
        }
        // If we're not provided store/update actions then let the form submit to itself.
        if ( ! isset($options['store']) && ! isset($options['update'])) {
            Arr::forget($options, 'model');

            return $this->form->model($model, $options);
        }
        if ( ! is_null($options['model']) && $options['model']->exists) {
            // If the form is passed a model, we'll use the update route to update
            // the model using the PUT method.
            $name = is_array($options['update']) ? Arr::first($options['update']) : $options['update'];
            $route = Str::contains($name, '@') ? 'action' : 'route';
            $options[$route] = array_merge((array)$options['update'], [$options['model']->getRouteKey()]);
            $options['method'] = 'PUT';
        } else {
            // Otherwise, we're storing a brand new model using the POST method.
            $name = is_array($options['store']) ? Arr::first($options['store']) : $options['store'];
            $route = Str::contains($name, '@') ? 'action' : 'route';
            $options[$route] = $options['store'];
            $options['method'] = 'POST';
        }
        // Forget the routes provided to the input.
        Arr::forget($options, ['model', 'update', 'store']);

        return $this->form->model($model, $options);
    }


    /**
     * Open a vertical (standard) Bootstrap form.
     */
    public function vertical(array $options = []): string
    {
        $this->setType(Type::VERTICAL);

        return $this->open($options);
    }


    /**
     * Open an inline Bootstrap form.
     */
    public function inline(array $options = []): string
    {
        $this->setType(Type::INLINE);

        return $this->open($options);
    }


    /**
     * Open a horizontal Bootstrap form.
     */
    public function horizontal(array $options = []): string
    {
        $this->setType(Type::HORIZONTAL);

        return $this->open($options);
    }


    /**
     * Create a Bootstrap static field.
     */
    public function staticField(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        $options = array_merge(['class' => $this->config->get('form_control_static_class', 'form-control-plaintext')], $options);

        if ( ! is_null($value)) {
            if (is_array($value) and isset($value['html'])) {
                $value = $value['html'];
            } else {
                $value = e($value);
            }
        }

        $label = $this->getLabelTitle($label, $name, $options);
        $comment = $this->getComment($options);
        $value = $value ?? e($this->form->getValueAttribute($name, null));
        $inputElement = '<p' . $this->html->attributes($options) . '>' . $value . '</p>';
        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $comment . '</div>';
        $groupOptions = $this->getGroupOptions($options);

        return $this->getFormGroupWithLabel($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Create a Bootstrap text field input.
     */
    public function text(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('text', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap email field input.
     */
    public function email(string $name = 'email', string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('email', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap URL field input.
     */
    public function url(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('url', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap tel field input.
     */
    public function tel(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('tel', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap number field input.
     */
    public function number(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('number', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap date field input.
     */
    public function date(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('date', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap time field input.
     */
    public function time(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('time', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap textarea field input.
     */
    public function textarea(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        return $this->input('textarea', $name, $label, $value, $options);
    }


    /**
     * Create a Bootstrap password field input.
     */
    public function password(string $name = 'password', string|bool|array|HtmlString|null $label = null, array $options = []): string
    {
        return $this->input('password', $name, $label, null, $options);
    }


    /**
     * Create a Bootstrap checkbox input.
     *
     * @param mixed       $value
     */
    public function checkbox(string $name, string|bool|array|HtmlString|null $label = null, $value = 1, ?bool $checked = null, array $options = []): string
    {
        $inputElement = $this->checkboxElement($name, $label, $value, $checked, false, $options);

        $wrapperOptions = $this->isHorizontal() ? [
            'class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])
        ] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . '</div>';

        return $this->getFormGroup(null, $wrapperElement);
    }


    /**
     * Create a single Bootstrap checkbox element.
     *
     * @param mixed       $value
     */
    public function checkboxElement(
        string $name,
        string|bool|array|HtmlString|null $label = null,
        $value = 1,
        ?bool $checked = null,
        bool $inline = false,
        array $options = []
    ): string {
        $wrapperClass = $inline ? 'form-check form-check-inline' : 'form-check';
        $label = $this->getLabelTitle($label, $name, $options, true) ?: '';
        $labelOptions = ['class' => 'form-check-label'];

        if ( ! isset($options['class'])) {
            $options['class'] = 'form-check-input';

            if ($label === null) {
                $options['class'] .= ' position-static';
            }
        }

        $options['class'] = trim($options['class'] . ' ' . $this->getFieldErrorClass($name));

        $label = ($label !== null ? $this->form->label(isset($options['id']) ? $options['id'] : $name, $label, $labelOptions) : '');
        $inputElement = $this->form->checkbox($name, $value, $checked, $options);
        $labelElement = $inputElement . $label . $this->getFieldError($name);

        return '<div class="' . $wrapperClass . '">' . $labelElement . '</div>';
    }


    /**
     * Create a collection of Bootstrap checkboxes.
     */
    public function checkboxes(
        string $name,
        string|bool|array|HtmlString|null $label = null,
        array $choices = [],
        array $checkedValues = [],
        bool $inline = false,
        array $options = []
    ): string {
        $elements = '';
        $index = 0;

        foreach ($choices as $value => $choiceLabel) {
            $checked = in_array($value, (array)$checkedValues);

            $elements .= $this->checkboxElement($name, $choiceLabel, $value, $checked, $inline, $options + ['id' => $name . ++$index]);
        }

        $comment = $this->getComment($options);
        $fieldError = $this->getFieldError($name);

        if ($inline) {
            $wrapperElement = $elements . ($fieldError ? sprintf('<span class="is-invalid"></span>%s', $fieldError) : $fieldError) . $comment;
        } else {
            $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
            $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $elements . ($fieldError ? sprintf('<span class="is-invalid"></span>%s', $fieldError) : $fieldError) . $comment . '</div>';
        }

        $groupOptions = $this->getGroupOptions($options + ['form-group-class' => 'form-checkboxes']);

        return $this->getFormGroupWithLabel($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Create a Bootstrap radio input.
     */
    public function radio(string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, ?bool $checked = null, array $options = []): string
    {
        $inputElement = $this->radioElement($name, $label, $value, $checked, false, $options);

        $wrapperOptions = $this->isHorizontal() ? [
            'class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])
        ] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . '</div>';

        return $this->getFormGroup(null, $wrapperElement);
    }


    /**
     * Create a single Bootstrap radio input.
     */
    public function radioElement(
        string $name,
        string|bool|array|HtmlString|null $label = null,
        ?string $value = null,
        ?bool $checked = null,
        bool $inline = false,
        array $options = []
    ): string {
        $wrapperClass = $inline ? 'form-check form-check-inline' : 'form-check';
        $label = $this->getLabelTitle($label, $name, Arr::except($options, 'required'), true) ?: '';
        $labelOptions = ['class' => 'form-check-label'];
        $displayError = ! Arr::exists($options, 'no-error');
        $options = Arr::except($options, 'no-error');

        $options['class'] = 'form-check-input' . (isset($options['class']) ? (' ' . $options['class']) : '');

        if ($label === null) {
            $options['class'] .= ' position-static';
        }

        $options['class'] = $options['class'] . ($displayError ? (' ' . $this->getFieldErrorClass($name)) : '');

        $label = ($label !== null ? $this->form->label(Arr::get($options, 'id') ?: $name, $label, $labelOptions) : '');
        $inputElement = $this->form->radio($name, $value, $checked, $options);
        $labelElement = $inputElement . $label . ($displayError ? $this->getFieldError($name) : '');

        return '<div class="' . $wrapperClass . '">' . $labelElement . '</div>';
    }


    /**
     * Create a collection of Bootstrap radio inputs.
     */
    public function radios(
        string $name,
        string|bool|array|HtmlString|null $label = null,
        array $choices = [],
        string|int|null $checkedValue = null,
        bool $inline = false,
        array $options = []
    ): string {
        $elements = '';
        $label = $this->getLabelTitle($label, $name, $options);
        $radioOptions = array_merge(['no-error' => true], $options);
        $index = 0;

        foreach ($choices as $value => $choiceLabel) {
            $checked = $value === $checkedValue;

            $elements .= $this->radioElement($name, $choiceLabel, $value, $checked, $inline, $radioOptions + ['id' => $name . ++$index]);
        }

        $comment = $this->getComment($options);

        if ($inline) {
            $wrapperElement = $elements . $this->getFieldError($name) . $comment;
        } else {
            $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
            $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $elements . $this->getFieldError($name) . $comment . '</div>';
        }

        $groupOptions = $this->getGroupOptions($options + [
                'form-group-class' => 'form-radios',
                'id' => $name,
            ]);
        $groupOptions['for'] = false;

        return $this->getFormGroupWithLabelForGroupedElements($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Create a Bootstrap label.
     */
    public function label(string $name, string|bool|null $value = null, array $options = []): string
    {
        if ($value === false) {
            return '';
        }

        $options = $this->getLabelOptions($options);
        $escapeHtml = false;

        if (is_array($value) and isset($value['html'])) {
            $value = $value['html'];
        } elseif ($value instanceof HtmlString) {
            $value = $value->toHtml();
        } else {
            $escapeHtml = true;
        }

        return $this->form->label($name, $value, $options, $escapeHtml);
    }


    /**
     * Create a Boostrap submit button.
     */
    public function submit(?string $value = null, array $options = []): string
    {
        $options = array_merge(['class' => 'btn btn-primary'], $options);

        $inputElement = $this->form->submit($value, $options);

        $wrapperOptions = $this->isHorizontal() ? [
            'class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])
        ] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . '</div>';

        return $this->getFormGroup(null, $wrapperElement);
    }


    /**
     * Create a Boostrap submit button.
     */
    public function button(?string $value = null, array $options = []): string
    {
        $options = array_merge(['class' => 'btn btn-primary'], $options);

        $inputElement = $this->form->button($value, $options);

        $wrapperOptions = $this->isHorizontal() ? [
            'class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])
        ] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . '</div>';

        return $this->getFormGroup(null, $wrapperElement);
    }


    /**
     * Create a Boostrap file upload button.
     */
    public function file(string $name, string|bool|array|HtmlString|null $label = null, array $options = []): string
    {
        $label = $this->getLabelTitle($label, $name, $options);
        $comment = $this->getComment($options);
        $options = array_merge(['class' => 'filestyle', 'data-buttonBefore' => 'true'], $options);

        $options = $this->getFieldOptions($options, $name);
        $inputElement = $this->form->input('file', $name, null, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $comment . '</div>';
        $groupOptions = $this->getGroupOptions($options);

        return $this->getFormGroupWithLabel($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Create the input group for an element with the correct classes for errors.
     */
    public function input(string $type, string $name, string|bool|array|HtmlString|null $label = null, ?string $value = null, array $options = []): string
    {
        $label = $this->getLabelTitle($label, $name, $options);
        $comment = $this->getComment($options);
        $options = $this->getFieldOptions($options, $name);
        $inputElement = $type === 'password' ? $this->form->password($name, $options) : $this->form->{$type}($name,
            $value, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $comment . '</div>';
        $groupOptions = $this->getGroupOptions($options);

        return $this->getFormGroupWithLabel($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Create a hidden field.
     */
    public function hidden(string $name, ?string $value = null, array $options = []): string
    {
        return $this->form->hidden($name, $value, $options);
    }


    /**
     * Create a select box field.
     */
    public function select(
        string $name,
        string|bool|array|HtmlString|null $label = null,
        array|Arrayable $list = [],
        string|array|null $selected = null,
        array $options = []
    ): string
    {
        $label = $this->getLabelTitle($label, $name, $options);
        $comment = $this->getComment($options);
        $options = $this->getFieldOptions($options, $name);
        $inputElement = $this->form->select($name, $list, $selected, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $comment . '</div>';
        $groupOptions = $this->getGroupOptions($options);

        return $this->getFormGroupWithLabel($name, $label, $wrapperElement, $groupOptions);
    }


    /**
     * Get the label title for a form field, first by using the provided one
     * or titleizing the field name.
     */
    protected function getLabelTitle(string|bool|array|HtmlString|null $label, string $name, array $options, bool $escapeHtml = false): string|bool
    {
        if ($label === false) {
            return false;
        }

        if (is_null($label) && Lang::has("forms.{$name}")) {
            return Lang::get("forms.{$name}");
        }

        if (is_array($label) and isset($label['html'])) {
            $label = $label['html'];
        } elseif ($label instanceof HtmlString) {
            $label = $label->toHtml();
        } else {
            $label = $escapeHtml ? e($label) : $label;
        }

        $label = $label ?: str_replace('_', ' ', Str::title($name));

        if (isset($options['required']) && $options['required']) {
            $required = $this->getLabelRequiredMark();
            $label = sprintf('%s %s', $label, $escapeHtml ? e($required) : $required);
        }

        return $escapeHtml ? $label : new HtmlString($label);
    }


    /**
     * Get a form group comprised of a label, form element and errors.
     */
    protected function getFormGroupWithLabel(string $name, string|bool|null $value, string $element, array $options = []): string
    {
        $for = ($options['for'] ?? null) === false ? null : (! empty($options['id']) ? $options['id'] : $name);
        $options = $this->getFormGroupOptions($name, Arr::except($options, 'id'));

        return '<div' . $this->html->attributes($options) . '>' . $this->label($for, $value) . $element . '</div>';
    }


    /**
     * Get a form group comprised of a label, form element and errors.
     */
    protected function getFormGroupWithLabelForGroupedElements(string $name, string|bool|null $value, string $element, array $options = []): string
    {
        $options = $this->getFormGroupOptions($name, Arr::except($options, 'id'));
        $legend = ! empty($value) ? ('<legend class="form-label">' . $value . '</legend>') : '';

        return '<fieldset' . $this->html->attributes($options) . '>' . $legend . $element . '</fieldset>';
    }


    /**
     * Get a form group.
     */
    public function getFormGroup(?string $name, string $element): string
    {
        $options = $this->getFormGroupOptions($name);

        return '<div' . $this->html->attributes($options) . '>' . $element . '</div>';
    }


    /**
     * Merge the options provided for a form group with the default options
     * required for Bootstrap styling.
     */
    protected function getFormGroupOptions(?string $name = null, array $options = []): array
    {
        $class = ['form-group'];

        if ($this->type == Type::HORIZONTAL) {
            $class[] = 'row';
        }

        if ($name) {
            $class[] = $this->getFormGroupErrorClass($name);
        }

        if (isset($options['required']) && $options['required']) {
            $class[] = $this->getGroupRequiredClass();

            Arr::forget($options, 'required');
        }

        if ( ! empty($options['form-group-class'])) {
            $class[] = $options['form-group-class'];

            Arr::forget($options, 'form-group-class');
        }

        $class = array_filter($class);

        return array_merge(['class' => join(' ', $class)], $options);
    }


    /**
     * Merge the options provided for a field with the default options
     * required for Bootstrap styling.
     */
    protected function getFieldOptions(array $options = [], ?string $name = null): array
    {
        $options['class'] = trim('form-control ' . $this->getFieldOptionsClass($options));
        $options['class'] = trim($options['class'] . ' ' . $this->getFieldErrorClass($name));

        // If we've been provided the input name and the ID has not been set in the options,
        // we'll use the name as the ID to hook it up with the label.
        if ($name && ! array_key_exists('id', $options)) {
            $options['id'] = $name;
        }

        return $options;
    }


    /**
     * Returns the class property from the options, or the empty string
     */
    protected function getFieldOptionsClass(array $options = []): ?string
    {
        return Arr::get($options, 'class');
    }


    /**
     * Merge the options provided for a label with the default options
     * required for Bootstrap styling.
     */
    protected function getLabelOptions(array $options = []): array
    {
        $class = 'control-label';
        if ($this->isHorizontal()) {
            $class .= ' ' . $this->getLeftColumnClass();
        }

        return array_merge(['class' => trim($class)], $options);
    }


    /**
     * Get the form type.
     */
    public function getType(): string
    {
        return isset($this->type) ? $this->type : $this->config->get('bootstrap_form.type');
    }


    /**
     * Determine if the form is of a horizontal type.
     */
    public function isHorizontal(): bool
    {
        return $this->getType() === Type::HORIZONTAL;
    }


    /**
     * Set the form type.
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }


    /**
     * Get the column class for the left column of a horizontal form.
     */
    public function getLeftColumnClass(): string
    {
        return $this->leftColumnClass ?: $this->config->get('bootstrap_form.left_column_class');
    }


    /**
     * Set the column class for the left column of a horizontal form.
     */
    public function setLeftColumnClass(string $class): void
    {
        $this->leftColumnClass = $class;
    }


    /**
     * Get the column class for the left column offset of a horizontal form.
     */
    public function getLeftColumnOffsetClass(): string
    {
        return $this->leftColumnOffsetClass ?: $this->config->get('bootstrap_form.left_column_offset_class');
    }


    /**
     * Set the column class for the left column offset of a horizontal form.
     */
    public function setLeftColumnOffsetClass(string $class): void
    {
        $this->leftColumnOffsetClass = $class;
    }


    /**
     * Get the column class for the right column of a horizontal form.
     */
    public function getRightColumnClass(): string
    {
        return $this->rightColumnClass ?: $this->config->get('bootstrap_form.right_column_class');
    }


    /**
     * Set the column class for the right column of a horizontal form.
     */
    public function setRightColumnClass(string $class): void
    {
        $this->rightColumnClass = $class;
    }


    /**
     * Get the label suffix appended when a field is required
     */
    public function getLabelRequiredMark(): string
    {
        return $this->labelRequiredMark ?: $this->config->get('bootstrap_form.label_required_mark');
    }


    /**
     * Set the label suffix appended when a field is required
     */
    public function setLabelRequiredMark(string $mark)
    {
        $this->labelRequiredMark = $mark;
    }


    /**
     * Get the invalid note mode (feedback or tooltip)
     */
    public function getInvalidMode(): string
    {
        return $this->invalidMode ?: 'feedback';
    }


    /**
     * Set the invalid note mode (feedback or tooltip)
     */
    public function setInvalidMode(string $mode)
    {
        $this->invalidMode = $mode;
    }


    /**
     * Get the CSS class added to the form group when a field is required
     */
    public function getGroupRequiredClass(): string
    {
        return $this->groupRequiredClass ?: $this->config->get('bootstrap_form.group_required_class');
    }


    /**
     * Set the CSS class added to the form group when a field is required
     */
    public function setGroupRequiredClass(string $cssClass)
    {
        $this->groupRequiredClass = $cssClass;
    }


    /**
     * Set the errorBag used for validation
     *
     * @param $errorBag
     */
    protected function setErrorBag($errorBag): void
    {
        $this->errorBag = $errorBag;
    }


    /**
     * Set the errorBag used for validation
     *
     * @param $values
     */
    protected function setDefaultValues($values): void
    {
        $this->form->setDefaultValues($values);
    }


    /**
     * Flatten arrayed field names to work with the validator, including removing "[]",
     * and converting nested arrays like "foo[bar][baz]" to "foo.bar.baz".
     */
    public function flattenFieldName(string $field): string
    {
        return preg_replace_callback("/\[([^]]*)]/U", function ($matches) {
            if ( ! empty($matches[1]) || $matches[1] === '0') {
                return '.' . $matches[1];
            }

            return '';
        }, $field);
    }


    /**
     * Get the MessageBag of errors that is populated by the
     * validator.
     */
    protected function getErrors(): MessageBag|ViewErrorBag|null
    {
        return $this->form->getSessionStore()->get('errors');
    }


    /**
     * Get the first error for a given field, using the provided
     * format, defaulting to the normal Bootstrap 3 format.
     *
     *
     * @return mixed
     */
    protected function getFieldError(string $field, ?string $format = null)
    {
        if ($format === null) {
            $format = sprintf('<div class="invalid-%s">:message</div>', $this->getInvalidMode());
        }

        $field = $this->flattenFieldName($field);

        if (isset($this->fieldsError[$field])) {
            return $this->fieldsError[$field];
        }

        if ($this->getErrors()) {
            $allErrors = $this->config->get('bootstrap_form.show_all_errors');

            if ($allErrors) {
                return $this->fieldsError[$field] = implode('', $this->getErrors()->{$this->errorBag}->get($field, $format));
            }

            return $this->fieldsError[$field] = $this->getErrors()->{$this->errorBag}->first($field, $format);
        }

        return $this->fieldsError[$field] = null;
    }


    /**
     * Return the error class to add onto the input field
     */
    protected function getFieldErrorClass(string $name): string
    {
        if ( ! empty($this->getFieldError($name))) {
            return 'is-invalid';
        }

        if ($this->isPostBack) {
            return 'is-valid';
        }

        return '';
    }


    /**
     * Return the error class if the given field has associated
     * errors, defaulting to the normal Bootstrap 3 error class.
     */
    protected function getFormGroupErrorClass(string $field, string $class = 'has-error'): ?string
    {
        return $this->getFieldError($field) ? $class : null;
    }


    /**
     * Returns the formatted comment from the options array
     *
     *
     * @return mixed|string
     */
    protected function getComment(array &$options, string $format = '<p class="form-text text-muted">:comment</p>')
    {
        $comment = Arr::pull($options, 'comment');

        if ( ! empty($comment)) {
            $comment = str_replace(':comment', e($comment), $format);
        } else {
            $comment = '';
        }

        return $comment;
    }


    protected function getGroupOptions(array $options = []): array
    {
        return Arr::only($options, ['required', 'form-group-class', 'id']);
    }
}
