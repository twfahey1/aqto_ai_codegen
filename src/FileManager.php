<?php

declare(strict_types=1);

namespace Drupal\aqto_ai_codegen;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Http\Client\ClientInterface;

/**
 * @todo Add class description.
 */
final class FileManager {

  /**
   * Constructs a FileManager object.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Lists files and folders in a given directory, distinguishing between the two.
   * 
   * @param string $directory The directory to scan.
   * @return array An array with 'files' and 'folders' keys, each containing respective items.
   */
  public function listFilesInDirectory(string $directory): array {
    $realPath = $this->fileSystem->realpath($directory);

    if ($realPath === FALSE || !is_dir($realPath)) {
      throw new \InvalidArgumentException("The specified directory does not exist or is not accessible.");
    }

    $dirEntries = scandir($realPath);
    $result = ['files' => [], 'folders' => []];

    foreach ($dirEntries as $entry) {
      $fullPath = $realPath . DIRECTORY_SEPARATOR . $entry;

      if (is_file($fullPath)) {
        $result['files'][] = $fullPath;
      } elseif (is_dir($fullPath) && $entry !== "." && $entry !== ".." && $entry !== ".git") {
        $result['folders'][] = $fullPath;
      }
    }

    return $result;
  }


  /**
   * A utility to get all the files and nested files in a single array with absolute paths.
   */
  public function listFilesInModule(string|NULL $module_name): array
  {
    if (!$module_name) {
      return [];
    }
    $fileManager = \Drupal::service('aqto_ai_codegen.file_manager');
    $module_path = \Drupal::service('extension.list.module')->getPath($module_name);
    try {
      $directoryContents = $fileManager->listFilesInDirectory($module_path);

      $fileOptions = [];
      foreach ($directoryContents['files'] as $file) {
        $fileOptions[$file] = $file;
      }
      // We want to go 2 folder levels deep, iterating and including those files and paths for selection.
      foreach ($directoryContents['folders'] as $directory) {
        $subDirectoryContents = $fileManager->listFilesInDirectory($directory);
        foreach ($subDirectoryContents['files'] as $file) {
          $fileOptions[$file] = $file;
        }

        // We want to go to a Subsub level
        foreach ($subDirectoryContents['folders'] as $subDirectory) {
          $subSubDirectoryContents = $fileManager->listFilesInDirectory($subDirectory);
          foreach ($subSubDirectoryContents['files'] as $file) {
            $fileOptions[$file] = $file;
          }

          // We want to go to a Subsubsub level
          foreach ($subSubDirectoryContents['folders'] as $subSubDirectory) {
            $subSubSubDirectoryContents = $fileManager->listFilesInDirectory($subSubDirectory);
            foreach ($subSubSubDirectoryContents['files'] as $file) {
              $fileOptions[$file] = $file;
            }
          }
        }

        return $fileOptions;
      }
    } catch (\InvalidArgumentException $e) {
      return [];
    }
  }

  /**
   * A writeFile method that will write a file to a specified directory.
   * 
   * @param string $filepath The absolute path to write.
   * 
   * @return bool TRUE if the file was written successfully, FALSE otherwise.
   * 
   */
  public function writeFile(string $filepath, string $content): bool {
    // We want to make sure we generate subfolders if necessary to write the file properly.
    $directory = dirname($filepath);
    if (!is_dir($directory)) {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    }
    return file_put_contents($filepath, $content) !== FALSE;
  }
}
