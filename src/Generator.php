<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen;

use Drupal\aqto_ai_core\Utilities;

/**
 * @todo Add class description.
 */
final class Generator
{

  /**
   * Constructs a Generator object.
   */
  public function __construct(
    private readonly Utilities $aqtoAiCoreUtilities,
  ) {
  }

  /**
   * A method to generate a module given the name.
   */
  public function generateModuleScaffold($module_name): void
  {
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
    $module_file_contents = "<?php\n\n";
    file_put_contents($module_file, $module_file_contents);


    // now lets install
    \Drupal::service('module_installer')->install([
      $module_name
    ], TRUE);

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
  public function generateThemeFunction($module_name, $hook_name, $variables): bool
{
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module_name;
    $module_file = $module_path . '/' . $module_name . '.module';

    // Ensure the directory exists
    if (!file_exists($module_path) && !mkdir($module_path, 0777, true)) {
        return false; // Failed to create directory
    }

    // Initialize or read the module file
    $file_needs_php_open_tag = false;
    if (!file_exists($module_file)) {
        $file_needs_php_open_tag = true;
        $module_file_contents = "";
    } else {
        $module_file_contents = file_get_contents($module_file);
    }

    // Check if the theme function already exists
    if (!preg_match('/function\s+' . $module_name . '_theme\s*\(\)\s*{\s*return\s*\[/', $module_file_contents)) {
        $module_file_contents .= ($file_needs_php_open_tag ? "<?php\n\n" : "\n") . "function {$module_name}_theme() {\n  return [\n  ];\n}\n";
    }

    // Add new hook to theme function if it doesn't exist
    if (strpos($module_file_contents, "'$hook_name' =>") === false) {
        $new_hook_content = $this->buildThemeFunction($hook_name, $variables);
        $module_file_contents = $this->addNewHookToThemeFunction($module_file_contents, $new_hook_content);
    }

    // Write the updated contents back to the module file
    return file_put_contents($module_file, $module_file_contents) !== false;
}

private function addNewHookToThemeFunction($existing_contents, $new_hook_content): string
{
    $pattern = '/(\s*\])\s*;\s*\}\s*$/'; // Matches the closing parts of the theme function
    $replacement = "    $new_hook_content,\n  $1;\n}"; // Correctly places the new hook and ensures the function closes properly
    return preg_replace($pattern, $replacement, $existing_contents);
}

private function buildThemeFunction($hook_name, $variables): string
{
    return "'$hook_name' => [\n" .
           "      'variables' => [\n" .
           $this->formatVariables($variables) .
           "      ]\n" .
           "    ]";
}

private function formatVariables($variables): string
{
    $variables = array_unique($variables); // Ensure no duplicates
    $variable_strings = [];
    foreach ($variables as $variable_key => $variable_type) {
        $variable_strings[] = "        '$variable_key' => NULL";
    }
    return implode(",\n", $variable_strings) . "\n";
}





  /**
   * A public function to generate a new Paragraph entity with desired fields.
   */
  public function generatePluginBlockAndThemeAndTemplate($module, $block_name, $variables, $theme_name)
  {
    // the  $theme_template_name should be  $theme_template_name = $module . '-' . $block_name . '.html.twig'; with all _ replaced with slash
    $theme_template_name = str_replace('_', '-', $theme_name) . '.html.twig';
    /**
     * WE want to generate files for a block, theme, and template.
     * Lets assume we have the module, if we dont, lets call the function to create it. 
     * - THen, we need to implement a a new theme function that will be used to render the block. This happens inside the $module.module file via a hook_theme(). Be sure to respeect existing hook_theme() functions if exist or create new if needed.
     * - Next we'll scaffold the plugin block and write that data, we want to make sure to include the primitive types e.g. string, number, etc. fields from those arrays. It should map to our theme and implement a render array like #theme and all the necessary variables. 
     *  -Once we've written the plugin block, we'll create a new twig template that leverages TailwindCSS classes to make a presentation for all the primitive types. This should have already been referenced in the step where we implemented hook_theme()/
     */
    $this->generateThemeFunction($module, $theme_name, $variables);
    $this->generatePluginBlock($module, $block_name, $variables, $theme_name);
    $this->generateTwigTemplate($module, $theme_name, $block_name, $variables, $theme_template_name);
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
  public function generateTwigTemplate($module, $theme_name, $block_name, $variables, $theme_template_name)
  {
    // Get the template getComponentAppearanceMarkupJson
    $template_data = $this->getComponentAppearanceMarkupJson(json_encode($variables), $theme_name);
    $template_markup = $template_data['template_markup'];
    $sample_data = $template_data['sample'];

    // Create the dir
    $current_path = getcwd();
    $module_path = $current_path . '/modules/' . $module;
    $template_path = $module_path . '/templates';
    if (!file_exists($template_path)) {
      mkdir($template_path, 0777, TRUE);
    }
    // Create the file
    $template_file = $template_path . '/' . $theme_template_name;
    $template_file_contents = $template_markup;

    file_put_contents($template_file, $template_file_contents);
    return TRUE;
  }


  /**
   * A callback to get an openAiResponse for the questino of "provide tailwind based html markup only that is for a block, the data for the block is $data, and then design ideas are $appearance".
   * 
   * It should return JSON with 'template_markup' which should be twig ready for block and 'sample' with key value pairs of sample data.
   * 
   */
  public function getComponentAppearanceMarkupJson(string $data, string $appearance)
  {
    $question_for_openai = "You are providing comprehensive and professional markup for a Tailwind based component. The markup should provide a comprehensive and detailed component based on the requirements, using as many Tailwind classes as is necessary, including responsive variants when necessary. It will be used in our Drupal site with Twig variables inserted. RETURN ONLY JSON with key of 'template_markup' that contains a twig template, and then also a 'sample' which contains the relevant key and values of the data with a random fun sample values. For reference here is the base64 encoded variables that you must use to match our already generated theme function: " . base64_encode($data) . ", and here are the appearance requirements to assist you as you consider the Tailwind classes, base64 encoded: " . base64_encode($appearance);
    $component_data_json = \Drupal::service('aqto_ai_core.utilities')->getOpenAiJsonResponse($question_for_openai);
    
    return $component_data_json;
  }
}
