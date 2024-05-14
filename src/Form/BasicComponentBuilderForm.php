<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Aqto AI Core form.
 */
final class BuilderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aqto_ai_codegen_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Lets wrap the form in some tailwind classes to give it marginss all around
    $form['#prefix'] = '<div class="p-4"><h1 class="text-2xl font-black">Basic Component Builder</h1><p>This tool will build a basic component in a module based on the provided prompts. The strategy is to make a Plugin Block wired to a new theme function, which is wired to an accompanying twig template, and a libraries.yml will be created or updated if any external CSS/JS libraries are required for functionality.</p>';
    $form['#suffix'] = '</div>';
    $form['output'] = [
      '#type' => 'markup',
      '#markup' => '<div id="message-output"></div>',
    ];
    // A select list that allows picking one of the sites enabled modules.
    $enabled_modules = \Drupal::moduleHandler()->getModuleList();
    $form['module_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Module selection'),
    ];
    $form['module_fieldset']['module'] = [
      '#type' => 'select',
      '#title' => $this->t('Pick the module to build the component in:'),
      '#options' => array_combine(array_keys($enabled_modules), array_keys($enabled_modules)),
      '#required' => TRUE,
      '#prefix' => '<div id="module-output">',
      '#suffix' => '</div>',
    ];
    // Lets add a "Refresh modules" that will invoke an ajax we'll write that refreshes the list
    $form['module_fieldset']['refresh'] = [
      '#type' => 'button',
      '#value' => $this->t('Refresh modules'),
      '#ajax' => [
        'callback' => '::refreshModulesAjax',
        'wrapper' => 'module-output',
      ],
    ];

    // Lets start a fieldgroup area for "Component Builder"
    $form['component_builder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Component Builder'),
    ];

    // A textfield for the component name.
    $form['component_builder']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the component e.g. "ContactInfoProfile".'),
      '#required' => TRUE,
    ];

    // A textfield for the template name.
    $form['component_builder']['template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Template file name'),
      '#description' => $this->t('The name of the theme and template file to use, typically matching the name above, e.g. "contact-info-profile".'),
      '#required' => TRUE,
    ];

    // A textarea to describe the data of the component.
    $form['component_builder']['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data / Variables'),
      '#description' => 'List the variables that describe the data structure of the component such as "text_to_display", "media_field"."',
      '#required' => TRUE,
    ];

    // A textarea to describe the appearance of the component.
    $form['component_builder']['appearance'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Appearance and functionality specs'),
      '#description' => 'Describe the appearance and functionality of the component. Be as specific as possible. e.g. "Implements the ScrollMagic.js library to make the text size bigger as the user scrolls down the page."',
      '#required' => TRUE,
    ];

    // Lets add an ajax callback submit now to do a upcoming function we'll write in a minute.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Build'),
        '#ajax' => [
          'callback' => '::buildComponentAjax',
          'wrapper' => 'component-output',
        ],
      ],
    ];

    return $form;
  }

  /**
   * The refreshModulesAjax callback
   */
  public function refreshModulesAjax(array &$form, FormStateInterface $form_state): array {
    // Lets get the enabled modules.
    // Lets return the response.
    return $form['module_fieldset']['module'];
  }

  /**
   * AJAX callback to process the input message and interact with AI.
   */
  public function buildComponentAjax(array &$form, FormStateInterface $form_state): array {
    // Lets get the form values.
    $module = $form_state->getValue('module');
    $name = $form_state->getValue('name');
    $template = $form_state->getValue('template');
    $data = $form_state->getValue('data');
    $appearance = $form_state->getValue('appearance');

    $siteActionsManager = \Drupal::service('aqto_ai_core.site_actions_manager');
    $message = 'action: create_a_plugin_block_and_theme_and_template_aka_component, module ' . $module . ', name: ' . $name . ', template: ' . $template . ', data: ' . $data . ', appearance specs:' . $appearance;
    $response = $siteActionsManager->invokeActionableQuestion($message);


    // Lets create a response object.
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#message-output', $this->t('Created component in the module @module with name @name, template @template, data @data, appearance @appearance', [
      '@module' => $module,
      '@name' => $name,
      '@template' => $template,
      '@data' => $data,
      '@appearance' => $appearance,
    ])));

    // Lets build the component.
    $component = [
      'name' => $name,
      'template' => $template,
      'data' => $data,
      'appearance' => $appearance,
    ];

    // Lets return the component as a render array.
    return [
      '#type' => 'markup',
      '#markup' => '<pre>' . print_r($component, TRUE) . '</pre>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    
  }

}
