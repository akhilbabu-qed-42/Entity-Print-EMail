<?php

declare(strict_types=1);

namespace Drupal\my_module\Plugin\QueueWorker;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'my_module_pdf_remover' queue worker.
 *
 * @QueueWorker(
 *   id = "my_module_pdf_remover",
 *   title = @Translation("Pdf remover"),
 *   cron = {"time" = 60},
 * )
 */
final class PdfRemover extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new PdfRemover instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Get the uri of the file to remove.
    $uri = $data->uri;
    // Delete the file.
    $this->fileSystem->delete($uri);
  }

}
