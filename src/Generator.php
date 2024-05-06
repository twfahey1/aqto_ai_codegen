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
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module_name;
    $module_file = $module_path . '/' . $module_name . '.module';

    // Ensure the directory exists
    if (!file_exists($module_path)) {
        mkdir($module_path, 0777, true); // Make sure to handle permission correctly
    }

    // Initialize the module file if it doesn't exist
    if (!file_exists($module_file)) {
        file_put_contents($module_file, "<?php\n\n");
    }

    $module_file_contents = file_get_contents($module_file);

    // Check if the theme function already exists to avoid duplicate entries
    if (!strpos($module_file_contents, "function {$module_name}_theme()")) {
        $module_name_theme = $module_name . '_theme';
        $hook_theme = "function $module_name_theme() {\n" .
          "  return [\n" .
          "    '$hook_name' => [\n" .
          "      'variables' => [\n";

        foreach ($variables as $variable) {
            $hook_theme .= "        '$variable' => NULL,\n";
        }

        $hook_theme .= "      ],\n" .
          "    ],\n" .
          "  ];\n" .
          "}\n";

        $module_file_contents .= $hook_theme;
        file_put_contents($module_file, $module_file_contents);
    }

    return TRUE;
}

  /**
   * A public function to generate a new Paragraph entity with desired fields.
   */
  public function generatePluginBlockAndThemeAndTemplate($module, $block_name, $block_primitive_values, $theme_name) {
    // the  $theme_template_name should be  $theme_template_name = $module . '-' . $block_name . '.html.twig'; with all _ replaced with slash
    $theme_template_name = str_replace('_', '-', $theme_name) . '.html.twig';
    /**
     * WE want to generate files for a block, theme, and template.
     * Lets assume we have the module, if we dont, lets call the function to create it. 
     * - THen, we need to implement a a new theme function that will be used to render the block. This happens inside the $module.module file via a hook_theme(). Be sure to respeect existing hook_theme() functions if exist or create new if needed.
     * - Next we'll scaffold the plugin block and write that data, we want to make sure to include the primitive types e.g. string, number, etc. fields from those arrays. It should map to our theme and implement a render array like #theme and all the necessary variables. 
     *  -Once we've written the plugin block, we'll create a new twig template that leverages TailwindCSS classes to make a presentation for all the primitive types. This should have already been referenced in the step where we implemented hook_theme()/
     */
    $this->generateThemeFunction($module, $theme_name, $block_primitive_values);
    $this->generatePluginBlock($module, $block_name, $block_primitive_values, $theme_name);
    $this->generateTwigTemplate($module, $theme_name, $block_name,  $block_primitive_values, $theme_template_name);

  }

  /**
   * A generatePluginBlock method to scaffold a new block plugin.
   * 
   * We have to:
   * - build a $module/src/Plugin/Block directory if it does not exist.
   * - build a new block plugin file with the desired block name.
   * - scaffold the block plugin file with the desired block name and primitive values.
   * - save the file and return true.
   * 
   */
  public function generatePluginBlock($module, $block_name, $block_primitive_values, $theme_name)
  {
      // Create the directories
      $current_path = getcwd();
      // Lets make sure our plugin block file is properly cased like if the block_name is "Hero photo" we want it to be HeroPhoto.php
      $block_name = str_replace(' ', '', ucwords($block_name));
      $module_path = $current_path . '/modules/' . $module;
      $block_path = $module_path . '/src/Plugin/Block';
      if (!file_exists($block_path)) {
        mkdir($block_path, 0777, TRUE);
      }
  
      // Create the block file
      $block_file = $block_path . '/' . $block_name . '.php';
      $block_file_contents = "<?php\n\n";
      $block_file_contents .= "namespace Drupal\\$module\\Plugin\\Block;\n\n";
      $block_file_contents .= "use Drupal\\Core\\Block\\BlockBase;\n";
      $block_file_contents .= "use Drupal\\Core\\Form\\FormStateInterface;\n";
      $block_file_contents .= "use Symfony\\Component\\DependencyInjection\\ContainerInterface;\n";
  
      $block_file_contents .= "/**\n";
      $block_file_contents .= " * Provides a '$block_name' block.\n";
      $block_file_contents .= " *\n";
      $block_file_contents .= " * @Block(\n";
      $block_file_contents .= " *   id = \"$block_name\",\n";
      $block_file_contents .= " *   admin_label = @Translation(\"$block_name\"),\n";
      $block_file_contents .= " *   category = @Translation(\"$module\"),\n";
      $block_file_contents .= " * )\n";
      $block_file_contents .= " */\n";
      $block_file_contents .= "class $block_name extends BlockBase {\n";
  
      // Define the block configuration
      $block_file_contents .= "  /**\n";
      $block_file_contents .= "   * {@inheritdoc}\n";
      $block_file_contents .= "   */\n";
      $block_file_contents .= "  public function defaultConfiguration() {\n";
      $block_file_contents .= "    return [\n";
      foreach ($block_primitive_values as $field_name => $type) {
          $block_file_contents .= "      '$field_name' => '',\n";
      }
      $block_file_contents .= "    ] + parent::defaultConfiguration();\n";
      $block_file_contents .= "  }\n";
  
      // Add block form for configuration
      $block_file_contents .= "  /**\n";
      $block_file_contents .= "   * {@inheritdoc}\n";
      $block_file_contents .= "   */\n";
      $block_file_contents .= "  public function blockForm(\$form, FormStateInterface \$form_state) {\n";
      $block_file_contents .= "    \$form = parent::blockForm(\$form, \$form_state);\n";
      $block_file_contents .= "    \$config = \$this->getConfiguration();\n";
      foreach ($block_primitive_values as $field_name => $type) {
          $block_file_contents .= "    \$form['$field_name'] = [\n";
          $block_file_contents .= "      '#type' => '$type',\n";
          $block_file_contents .= "      '#title' => t('" . ucfirst($field_name) . "'),\n";
          $block_file_contents .= "      '#default_value' => isset(\$config['$field_name']) ? \$config['$field_name'] : '',\n";
          $block_file_contents .= "    ];\n";
      }
      $block_file_contents .= "    return \$form;\n";
      $block_file_contents .= "  }\n";
  
      // Save block configuration
      $block_file_contents .= "  /**\n";
      $block_file_contents .= "   * {@inheritdoc}\n";
      $block_file_contents .= "   */\n";
      $block_file_contents .= "  public function blockSubmit(\$form, FormStateInterface \$form_state) {\n";
      $block_file_contents .= "    parent::blockSubmit(\$form, \$form_state);\n";
      foreach ($block_primitive_values as $field_name => $type) {
          $block_file_contents .= "    \$this->configuration['$field_name'] = \$form_state->getValue('$field_name');\n";
      }
      $block_file_contents .= "  }\n";
  
      // Build the block content
      $block_file_contents .= "  /**\n";
      $block_file_contents .= "   * {@inheritdoc}\n";
      $block_file_contents .= "   */\n";
      $block_file_contents .= "  public function build() {\n";
      $block_file_contents .= "    \$config = \$this->getConfiguration();\n";
      $block_file_contents .= "    return [\n";
      $block_file_contents .= "      '#theme' => '$theme_name',\n";
      foreach ($block_primitive_values as $field_name => $type) {
          $block_file_contents .= "      '#$field_name' => \$config['$field_name'],\n";
      }
      $block_file_contents .= "    ];\n";
      $block_file_contents .= "  }\n";
  
      $block_file_contents .= "}\n";
  
      file_put_contents($block_file, $block_file_contents);
  
      return TRUE;
  }
  

  /**
   * Same for twig template.
   * 
   * We have to:
   * - build a $module/templates directory if it does not exist.
   * - build a new twig template file with the desired template name.
   * - scaffold the twig template file with the desired template name.
   * - save the file and return true.
   * 
   */
  public function generateTwigTemplate($module, $theme_name, $block_name,  $block_primitive_values, $theme_template_name)
  {
    // Create the dir
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module;
    $template_path = $module_path . '/templates';
    if (!file_exists($template_path)) {
      mkdir($template_path, 0777, TRUE);
    }
    // Create the file
    $template_file = $template_path . '/' . $theme_template_name;
    $template_file_contents = "<div class='grid grid-cols-2 gap-4'>\n";
    foreach ($block_primitive_values as $key => $value) {
      $template_file_contents .= "  <div class='bg-gray-100 p-4'>\n";
      $template_file_contents .= "    <h2 class='text-lg font-bold'>$key</h2>\n";
      $template_file_contents .= "    <p>$value</p>\n";
      $template_file_contents .= "  </div>\n";
    }
    $template_file_contents .= "</div>\n";
    file_put_contents($template_file, $template_file_contents);
    return TRUE;
  }


}
