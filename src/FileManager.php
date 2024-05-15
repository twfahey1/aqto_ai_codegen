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
      } elseif (is_dir($fullPath) && $entry !== "." && $entry !== "..") {
        $result['folders'][] = $entry;
      }
    }

    return $result;
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
    return file_put_contents($filepath, $content) !== FALSE;
  }
}
