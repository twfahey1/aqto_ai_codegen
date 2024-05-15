<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Aqto AI Core form.
 */
final class WorkonForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'aqto_ai_codegen_workon';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['output'] = [
      '#type' => 'markup',
      '#markup' => '<div id="message-output"></div>',
    ];

    // Directory browser
    $form['directory_browser'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Directory Browser'),
    ];
    $form['directory_browser']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory path'),
      '#default_value' => '/',
      '#description' => $this->t('Enter a directory path to browse.'),
    ];
    $form['directory_browser']['browse'] = [
      '#type' => 'button',
      '#value' => $this->t('Browse'),
      '#ajax' => [
        'callback' => '::browseDirectoryAjax',
        'wrapper' => 'file-list',
      ],
    ];
    $form['directory_browser']['files'] = [
      '#type' => 'markup',
      '#markup' => '<div id="file-list"></div>',
    ];

    // Existing component building UI
    $form = $this->addExistingComponentBuilder($form, $form_state);

    return $form;
  }

  /**
   * AJAX callback for browsing directories.
   */
  /**
   * AJAX callback for browsing directories.
   */
  public function browseDirectoryAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $path = $form_state->getValue('path');
    $response = new Ajaxresponse();
    $fileManager = \Drupal::service('aqto_ai_codegen.file_manager');

    try {
      $directoryContents = $fileManager->listFilesInDirectory($path);

      $fileListMarkup = '<ul>';
      foreach ($directoryContents['folders'] as $folder) {
        $fileListMarkup .= '<li><strong>' . htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') . '</strong>/</li>';
      }
      foreach ($directoryContents['files'] as $file) {
        $fileListMarkup .= '<li>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</li>';
      }
      $fileListMarkup .= '</ul>';

      $response->addCommand(new ReplaceCommand('#file-list', $fileListMarkup));
    } catch (\InvalidArgumentException $e) {
      $response->addCommand(new ReplaceCommand('#file-list', '<div class="error-message">' . $this->t('Error: @error', ['@backslashError' => $e->getMessage()]) . '</div>'));
    }

    return $response;
  }



  /**
   * Method to fetch files from a directory.
   */
  protected function getFiles($path)
  {
    // Fetch and return list of files based on the directory path
    // This would likely use scandir($path) or similar PHP functions
    return []; // Example return, implement as needed
  }

  /**
   * Example existing method from provided code.
   */
  protected function addExistingComponentBuilder(array $form, FormStateInterface $form_state): array
  {
    // Your existing component builder code here...
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    // Add your form validation here
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    // Handle form submission here
  }
}
