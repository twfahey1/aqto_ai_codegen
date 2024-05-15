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
final class FeatureEnhancerForm extends FormBase {

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
      '#title' => $this->t('Pick the Feature module to enhance'),
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
    $form['component_builder']['module_requests'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Module requests'),
      '#description' => $this->t('Things we want to build in the module file preprocess, e.g. "Our awesome_profile Paragraph type should have a preprocess to render the image field with a nice border and request the latest weather from the open weather API for the profile location."'),
      '#required' => TRUE,
    ];

    // A textfield for the template name.
    $form['component_builder']['theme_requests'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Theme and template requests'),
      '#description' => $this->t('Provide as much detail as desired around the theme and template requests. e.g. "We want to show the location map in a modal when the user clicks the location name."'),
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
  public function buildComponentAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Lets get the form values.
    $module = $form_state->getValue('module');
    $module_requests = $form_state->getValue('module_requests');
    $template_design_requests = $form_state->getValue('theme_requests');


    $siteActionsManager = \Drupal::service('aqto_ai_core.site_actions_manager');
    $message = 'action: enhance_feature, module ' . $module . ', module_requests ' . $module_requests . ', template_design_requests ' . $template_design_requests;
    $response = $siteActionsManager->invokeActionableQuestion($message);


    // Lets create a response object.
    $response = new AjaxResponse();
    // Lets add a command to replace the output div with the response.
    $response->addCommand(new HtmlCommand('#message-output', $response));
    // Lets return the response.
    return $response;
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
