<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Aqto AI Core form.
 */
final class FeatureEnhancerForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'aqto_ai_codegen_feature_enhancer_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    // Lets wrap the form in some tailwind classes to give it margins all around
    $form['#prefix'] = '<div class="p-4"><h1 class="text-2xl font-black">Feature Enhancer Builder</h1><p>This tool will allow selecting a module, and optionally 1 or more files, to get updated based on the provided prompts. The strategy is to make a Plugin Block wired to a new theme function, which is wired to an accompanying twig template, and a libraries.yml will be created or updated if any external CSS/JS libraries are required for functionality.</p>';
    $form['#suffix'] = '</div>';
    $form['output'] = [
      '#type' => 'markup',
      '#markup' => '<div id="message-output"></div>',
    ];

    // A select list that allows picking one of the site's enabled modules.
    $enabled_modules = \Drupal::moduleHandler()->getModuleList();
    $form['module_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Module selection'),
    ];
    $form['module_fieldset']['module_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Pick the Feature module to enhance'),
      '#options' => array_combine(array_keys($enabled_modules), array_keys($enabled_modules)),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::loadModuleFilesAjax',
        'wrapper' => 'file-list',
      ],
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

    // Start a fieldgroup area for "Component Builder"
    $form['component_builder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Component Builder'),
    ];

    // File selector
    $file_manager = \Drupal::service('aqto_ai_codegen.file_manager');
    $files_for_selected_module = $file_manager->listFilesInModule($form_state->getValue('module_name'));
    $form['component_builder']['files_to_update'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Files to update'),
      '#options' => $files_for_selected_module,
      '#prefix' => '<div id="file-list">',
      '#suffix' => '</div>',
    ];

    // A textfield for the component name.
    $form['component_builder']['custom_requests'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom update requests'),
      '#description' => $this->t('Things about this update we want. We could do a general file operation like "Create a new file called foo.csv", or we could be more specific about including specific code, such as "Add a new field to the form that allows the user to upload a file.". The files selected above will be sent along with the request to form the new files that will be written.'),
      '#required' => TRUE,
    ];

    // A collapsible "detailed example" that is collapsed by default we can show a more advanced example.
    $form['component_builder']['detailed_example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example prompts'),
      '#open' => FALSE,
    ];
    $form['component_builder']['detailed_example']['example'] = [
      '#markup' => '<p>In our aqto_example_news_highlight.module, we want to add a hook_preprocess() for the news_highlight Paragraph type, and update to add a new hook_theme() called aqto_example_news_highlight with variables of headline and image.  In the preprocess, we should override the output with a custom #theme array to a newly written aqto_example_news_highlight theme function, and we should add a new twig template that can, for now, just render out the values for the headline and image fields as defined in the field config.</p><strong>Note: in this example, we picked the module, then selected the config yml files for the Paragraph and the fields we mention. Notice how we request for any new files, plus the existing files can also be considered.</strong>',
    ];
    // Add an ajax callback submit now to do a upcoming function we'll write in a minute.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Build'),
        '#ajax' => [
          'callback' => '::enhanceFeatureAjax',
          'wrapper' => 'component-output',
        ],
      ],
    ];

    return $form;
  }

  /**
   * The refreshModulesAjax callback
   */
  public function refreshModulesAjax(array &$form, FormStateInterface $form_state): array
  {
    // Get the enabled modules.
    $enabled_modules = \Drupal::moduleHandler()->getModuleList();
    $form['module_fieldset']['module_name']['#options'] = array_combine(array_keys($enabled_modules), array_keys($enabled_modules));

    // Return the response.
    return $form['module_fieldset'];
  }


  /**
   * AJAX callback to load files from the selected module.
   */
  public function loadModuleFilesAjax(array &$form, FormStateInterface $form_state): AjaxResponse
  {
    $module_name = $form_state->getValue('module_name');
    $fileManager = \Drupal::service('aqto_ai_codegen.file_manager');

    $response = new AjaxResponse();
    if ($module_name) {
      $module_path = \Drupal::service('extension.list.module')->getPath($module_name);
      try {
        $file_manager = \Drupal::service('aqto_ai_codegen.file_manager');
        $fileOptions = $file_manager->listFilesInModule($module_name);        
        $form['component_builder']['files_to_update']['#options'] = $fileOptions;

        $response->addCommand(new ReplaceCommand('#file-list', $form['component_builder']['files_to_update']));
      } catch (\InvalidArgumentException $e) {
        $response->addCommand(new HtmlCommand('#file-list', '<div class="error-message">' . $this->t('Error: @error', ['@error' => $e->getMessage()]) . '</div>'));
      }
    }

    return $response;
  }

  /**
   * AJAX callback to process the input message and interact with AI.
   */
  public function enhanceFeatureAjax(array &$form, FormStateInterface $form_state): AjaxResponse
  {
    // Get the form values.
    $module_name = $form_state->getValue('module_name');
    $absolute_path_to_module = \Drupal::service('extension.list.module')->getPath($module_name);
    // Get the realpath
    $absolute_path_to_module = \Drupal::service('file_system')->realpath($absolute_path_to_module);
    $files_to_update = array_filter($form_state->getValue('files_to_update'));
    $custom_requests = $form_state->getValue('custom_requests');

    // Get the service.
    $feature_enhancer = \Drupal::service('aqto_ai_codegen.feature_enhancer');

    // Invoke the feature enhancer.
    $response_message = $feature_enhancer->enhanceFilesInModule($module_name, $absolute_path_to_module, $files_to_update, $custom_requests);
    
    // Create a response object.
    $response = new AjaxResponse();
    // Add a command to replace the output div with the response.
    $response->addCommand(new HtmlCommand('#message-output', $response_message));

    // Return the response.
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    // Validate the form here if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    // Nothing in actual submit yet, all AJAX callbacks.
  }
}
