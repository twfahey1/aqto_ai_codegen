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
  public function getDesignSpecFromModuleConfig(string $moduleName, string $modulePath, $module_requests, $template_design_requests): array
  {
    $configInstallFilesBase64 = $this->getModuleConfigInstallFilesAsBase64($modulePath);
    // Get the realpath to the module file
    $moduleRealPath = \Drupal::service('file_system')->realpath($modulePath);
    $moduleFileBase64 = $this->getModuleFileContentsAsBase64($moduleName, $moduleRealPath);
    // Build the design_requirements
    $design_requirements = "Module requests: " . print_r($module_requests, TRUE) . ', Template design requests: ' . $template_design_requests;
    $prompt = json_encode([
      'design_requirements' => $design_requirements,
      'config_files' => $configInstallFilesBase64,  
      'module_file' => $moduleFileBase64,
      'module_path' => $modulePath,
      'module_name' => $moduleName,
      'module_requests' => $module_requests,
      'theme_requests' => $template_design_requests,
    ]);
    $prompt = 'CRITICAL: RETURN JSON ONLY! which provides revised copies of the files in a "revised_files" array, where each key is filename, and value is updated code base64 encoded to meet the design_requirements: Here is the data' . $prompt;
    $response = $this->aqtoAiCoreUtilities->getOpenAiJsonResponse($prompt);
    // At this point we have a 'revised_files' array, loop through and for each, base64 decode the 'value' key and then write it to the 'filename' path.
    foreach ($response['revised_files'] as $filename => $fileContentsBase64) {
      $fileContents = base64_decode($fileContentsBase64['value']);
      $this->fileManager->writeFile($fileContentsBase64['filename'], $fileContents);
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
    $design_spec_computed = $this->getDesignSpecFromModuleConfig($module_name, $modulePath, $module_requests, $template_design_requests);

    // Todo we need to process the payload
    foreach ($design_spec_computed['file_changes'] as $file_change) {
      $this->fileManager->writeFile($file_change['file_path'], base64_decode($file_change['new_contents']));
    }
    // Write the response to the disk.
    // $this->fileManager->writeFile($modulePath . '/design_spec.json', json_encode($response));
  }




}
