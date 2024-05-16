<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen;

use Drupal\aqto_ai_core\SiteActionsManager;
use Drupal\aqto_ai_core\SiteActionsTrait;
use Drupal\aqto_ai_core\Utilities;

/**
 * @todo Add class description.
 */
final class FeatureEnhancer
{

  use SiteActionsTrait;

  /**
   * Constructs a Generator object.
   */
  public function __construct(
    private readonly Utilities $aqtoAiCoreUtilities,
    private readonly FileManager $fileManager,
    private readonly SiteActionsManager $siteActionsManager,
  ) {
  }

  /**
   * A method that takes a path to a module, and retrieves the config/install/* files and returns an array of a list of those file paths.
   * 
   * @param string $modulePath
   *  The path to the module.
   * 
   * @return array
   * An array of file paths.
   */
  public function getModuleConfigInstallFiles(string $modulePath): array
  {
    $configInstallPath = $modulePath . '/config/install';
    // Get the absolute path to it to pass in
    $configInstallPath = \Drupal::service('file_system')->realpath($configInstallPath);
    $configInstallFiles = $this->fileManager->listFilesInDirectory($configInstallPath)['files'];
    return $configInstallFiles;
  }

  /**
   * A helper that will take an array of absolute file paths, and return a base encoded array of all the file contents.
   * 
   * Helpful for packaging up files for LLM processing.
   */
  public function getFilesContentsAsBase64(array $files): array
  {
    $fileContentsData = [];
    foreach ($files as $file) {
      $fileContents = file_get_contents($file);
      $fileContentsData[$file] = base64_encode($fileContents);
    }
    return $fileContentsData;
  }

  /**
   * A helper that can get all module config files in base64 encoded format.
   * 
   * @param string $modulePath
   * The path to the module.
   * 
   * @return array
   * An array of base64 encoded file contents.
   */
  public function getModuleConfigInstallFilesAsBase64(string $modulePath): array
  {
    $configInstallFiles = $this->getModuleConfigInstallFiles($modulePath);
    $configInstallFilesBase64 = $this->getFilesContentsAsBase64($configInstallFiles);
    return $configInstallFilesBase64;
  }

  /**
   * A helper to get *all* the files and folders recursively, returning a nicely structured array of full realpath to file => base64 encoded contents of file.
   * 
   * @param string $path
   * The path to the directory.
   * 
   * @return array
   * An array of file paths and base64 encoded file contents.
   */
  public function getAllFilesAndFoldersAsBase64(string $path): array
  {
    $files_and_folders = $this->fileManager->listFilesInDirectory($path);
    // We'll have an array of files with absolute paths, and array of folders. Let's ignore any folders prefixed with ".", and any others, we can recursively get those files and add to the overall array of files.
    $master_file_list_with_contents = [];
    foreach ($files_and_folders['files'] as $file) {
      $fileContents = file_get_contents($file);
      $master_file_list_with_contents[$file] = base64_encode($fileContents);
    }
    // Now we need to loop through the folders and recursively get the files and add to the master list.
    foreach ($files_and_folders['folders'] as $folder) {
      $folder_files = $this->fileManager->listFilesInDirectory($folder);
      foreach ($folder_files['files'] as $file) {
        $fileContents = file_get_contents($file);
        $master_file_list_with_contents[$file] = base64_encode($fileContents);
      }
      // Go one more level for config
      foreach ($folder_files['folders'] as $subfolder) {
        $subfolder_files = $this->fileManager->listFilesInDirectory($subfolder);
        foreach ($subfolder_files['files'] as $file) {
          $fileContents = file_get_contents($file);
          $master_file_list_with_contents[$file] = base64_encode($fileContents);
        }
      }
    }
    return $master_file_list_with_contents;
  }

  /**
   * A helper to get the contents of the $modulePath .module file and erturn it base 64 encoded.
   * 
   * @param string $modulePath
   *  The path to the module.
   * 
   * @return string
   *  The base64 encoded contents of the .module file.
   */
  public function getModuleFileContentsAsBase64(string $moduleName, string $modulePath): string
  {
    $moduleFile = $modulePath . '/' . $moduleName . '.module';
    // If the file doesnt exist we must scaffold it with just "<?php" as the contents.
    if (!file_exists($moduleFile)) {
      $this->fileManager->writeFile($moduleFile, "<?php");
    }
    $moduleFileContents = file_get_contents($moduleFile);
    return base64_encode($moduleFileContents);
  }

  /**
   * A function that takes module_name, an array of file paths, and text of custom_requests. We build an array of the file contents base64 encoded. Then we prompt openAI to generate a return array of "revised_files" with the paths and new contents included, considering the custom_requests.
   * 
   * @param string $module_name
   * The name of the module.
   * 
   * @param array $files_to_update
   * An array of file paths to update.
   * 
   * @param string $custom_requests
   * The text of the custom requests.
   * 
   * @return array
   * An array of the response data.
   */
  public function enhanceFilesInModule(string $module_name, string $absolute_path_to_module, array $files_to_update, string $custom_requests): array
  {
    // Fore ach of our fiels_to_update, loop through and generate th base74 contents
    $files_to_update_base64 = [];
    foreach ($files_to_update as $file) {
      $fileContents = file_get_contents($file);
      $files_to_update_base64[$file] = base64_encode($fileContents);
    }
    // Build the prompt
    $prompt = 'Return JSON response only.';
    $prompt .= 'You are providing file additions or revisions based on request in a JSON array structured like: {"revised_files":{"/path/to/file": "new_file_contents"}}';
    $prompt .= 'Every filename should prefix with the absolute path to where it needs to be written ultimately, which is ' . $absolute_path_to_module . '.';
    if (!empty($files_to_update)) {
      $prompt .= 'The user has indicated these files as part of the update context that may possibly need updating: ' . json_encode($files_to_update) . '.';
    }
    $prompt .= 'The updates specifically needed are: ' . $custom_requests . '.';

    $response = $this->aqtoAiCoreUtilities->getOpenAiJsonResponse($prompt);
    // Lets log out the raw response json for audting in watchdog
    \Drupal::logger('aqto_ai_codegen')->info('Raw response json: ' . json_encode($response));
    // At this point we have a 'revised_files' array, loop through and for each, base64 decode the 'value' key and then write it to the 'filename' path.
    foreach ($response['revised_files'] as $file_path => $file_val) {
      $this->fileManager->writeFile($file_path, $file_val);
    }

    return $this->getStandardizedResult('enhance_files_in_module', $response);

  }

  /**
   * Build a prompt that will indicate to the llm that they have base64 encoded config, and they want to return a json response with details about a design spec. Specifically, provide a 'module_hooks' array of any hook_preprocess_HOOK functions that can be written directly into the the .module of the project, a 'theme_hooks' that we'll add or ammend in the .module's hook_theme(), should include the 'variables' key and the 'template-name' key so we can write that data and create the template file.
   * 
   * @param string $modulePath
   * The path to the module.
   * 
   * @param array $module_requests
   * An array of preprocess requests in natural language, such as 'We want to display our image field in a nice styled round border with a hover effect'. These rqeuests will be sent to the LLM to consider in scaffolding any hook_preprocess_HOOK functions.
   * 
   * @param array $template_design_requests
   *  An array of template design requests. These will be sent to the LLM to consider in scaffolding any hook_theme() implementations and twig template files.
   * 
   * @return array
   * An array of the response data.
   */
  public function getDesignSpecFromModuleConfig(string $moduleName, string $modulePath, $module_files, $module_requests, $template_design_requests): array
  {
    $configInstallFilesBase64 = $this->getModuleConfigInstallFilesAsBase64($modulePath);
    // Get the realpath to the module file
    $moduleRealPath = \Drupal::service('file_system')->realpath($modulePath);
    // Build the design_requirements
    $design_requirements = "Design requirements: " . print_r($module_requests, TRUE) . ',' . $template_design_requests;
    $prompt = base64_encode(json_encode([
      'design_requirements' => $design_requirements,
      'module_files' => $module_files,
      'module_path' => $moduleRealPath,
      'module_name' => $moduleName,
    ]));
    $prompt = 'CRITICAL: RETURN JSON ONLY! Working with the Drupal 10 module data which has been freshly parsed. Consider the design_requirements compared with the module_files, and provide revised versions of the files that need changes in a "revised_files" array, where each key is filename, and value is updated code, and its base64 encoded. When appropriate, include new files, e.g. a libraries.yml for new assets used in the newly defined theme function, and for new files provide a reasonable file path name based on available context. Here is the JSON spec data in base64:' . $prompt;
    $response = $this->aqtoAiCoreUtilities->getOpenAiJsonResponse($prompt);
    // Lets log out the raw response json for audting in watchdog
    \Drupal::logger('aqto_ai_codegen')->info('Raw response json: ' . json_encode($response));
    // At this point we have a 'revised_files' array, loop through and for each, base64 decode the 'value' key and then write it to the 'filename' path.
    foreach ($response['revised_files'] as $file_key => $file_vals) {
      $fileContents = base64_decode($file_vals['content'] ?? $file_vals['value'] ?? '');
      $this->fileManager->writeFile($file_vals['filename'], $fileContents);
    }
    
    return $this->getStandardizedResult('get_design_spec_from_module_config_and_requests', $response);
  }

  /**
   * A function to enhanceFeature that takes a feature module name, plus the requests and design requests, gets the answer specsa back, then writes the file ops to the disk.
   */
  public function enhanceFeature(string $module_name, $module_requests, $template_design_requests): void
  {
    // Get path to module_name
    $modulePath = \Drupal::moduleHandler()->getModule($module_name)->getPath();
    // Get all the files in the module and the base64 encoded the contents.
    $module_files = $this->getAllFilesAndFoldersAsBase64($modulePath);
    $design_spec_computed = $this->getDesignSpecFromModuleConfig($module_name, $modulePath, $module_files, $module_requests, $template_design_requests);

    // Todo we need to process the payload
    foreach ($design_spec_computed['file_changes'] as $file_change) {
      $this->fileManager->writeFile($file_change['file_path'], base64_decode($file_change['new_contents']));
    }
    // Write the response to the disk.
    // $this->fileManager->writeFile($modulePath . '/design_spec.json', json_encode($response));
  }




}
