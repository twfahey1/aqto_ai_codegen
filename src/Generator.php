<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen;

use Drupal\aqto_ai_core\Utilities;

/**
 * @todo Add class description.
 */
final class Generator {

  /**
   * Constructs a Generator object.
   */
  public function __construct(
    private readonly Utilities $aqtoAiCoreUtilities,
  ) {}

  /**
   * A method to generate a module given the name.
   */
  public function generateModuleScaffold($module_name): void {
    // We want to take the module name and scaffold our files.
    // Let's use php to do this.
    // We will need to create a directory for the module.
    // We will need to create a .info.yml file.
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module_name;
    mkdir($module_path);
    $info_file = $module_path . '/' . $module_name . '.info.yml';
    $info_file_contents = "name: '$module_name'\n" .
      "type: module\n" .
      "description: 'A module'\n" .
      "core_version_requirement: 10.x\n" .
      "package: 'Custom'\n";
    
    file_put_contents($info_file, $info_file_contents);
    // We will need to create a .module file.
    $module_file = $module_path . '/' . $module_name . '.module';
  }

  /**
   * A method to generate a new theme function inside a given module name .module file.
   * 
   * We have to:
   * - Load the file
   * - Determine the current implementations in the file of a hook_theme() function. If it not exist yet, lets scffold it.
   * - Add the desired hook name and variable names to the hook_theme() function.
   * - Add the function to the file.
   * - Save file and return true
   */
  public function generateThemeFunction($module_name, $hook_name, $variables): bool {
    // We want to take the module name and scaffold our files.
    // Let's use php to do this.
    // We will need to create a directory for the module.
    // We will need to create a .info.yml file.
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module_name;
    $module_file = $module_path . '/' . $module_name . '.module';
    $module_file_contents = file_get_contents($module_file);
    $module_name_theme = $module_name . '_theme';
    $hook_theme = "function $module_name_theme() {\n" .
      "  return [\n";
    foreach ($variables as $variable) {
      $hook_theme .= "    '$hook_name' => [\n" .
        "      'variables' => [\n" .
        "        '$variable' => NULL,\n" .
        "      ],\n" .
        "    ],\n";
    }
    $hook_theme .= "  ];\n" .
      "}\n";
    $module_file_contents .= $hook_theme;
    file_put_contents($module_file, $module_file_contents);
    return TRUE;
  }
  

  /**
   * A public function to generate a new Paragraph entity with desired fields.
   */
  public function generateParagraphEntity(): void {
    // @todo Place your code here.
    
  }
}
