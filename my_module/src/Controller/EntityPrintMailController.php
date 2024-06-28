<?php

namespace Drupal\my_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The controller class.
 */
class EntityPrintMailController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly MailManagerInterface $pluginManagerMail,
    private readonly EntityPrintPluginManagerInterface $pluginManagerEntityPrintPrintEngine,
    private readonly PrintBuilderInterface $entityPrintPrintBuilder,
    private readonly FileSystemInterface $fileSystem,
    private readonly QueueFactory $queueFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.mail'),
      $container->get('plugin.manager.entity_print.print_engine'),
      $container->get('entity_print.print_builder'),
      $container->get('file_system'),
      $container->get('queue')
    );
  }

  /**
   * Build the response.
   */
  public function process(NodeInterface $node_id) {
    // Prepare the destination folder if it does not exist.
    if ($this->prepareDestinationFolder()) {
      // Generate the PDF from the node.
      $data = $this->generatePdfFromNode($node_id);
      if (!empty($data)) {
        // Pass the 'uri' and 'print engine' values to attach the pdf and send
        // the mail.
        $result = $this->sendMail($data['uri'], $data['print_engine']);
        // If $result = TRUE, Mail has been sent successfully.
        if ($result) {
          $message = $this->t('Email sent successfully');
          $this->messenger()->addStatus($message);
          // Add the generated file's uri to the queue so that it can be
          // deleted later.
          $queue = $this->queueFactory->get('my_module_pdf_remover');
          $item = new \stdClass();
          $item->uri = $data['uri'];
          $queue->createItem($item);
        }
      }
    }
    // Redirect back to the node.
    return $this->redirect('entity.node.canonical', ['node' => $node_id->id()]);
  }

  /**
   * Prepares the folder to store the generated PDFs.
   */
  public function prepareDestinationFolder() {
    $destination_folder = 'public://emailed_pdfs';
    // Try to create the directory.
    if ($this->fileSystem->prepareDirectory($destination_folder, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      // Return 'TRUE' if the folder was successfully created.
      return TRUE;
    }
    else {
      // Return 'FALSE' in case of any error.
      return FALSE;
    }
  }

  /**
   * Generates the pdf from the node.
   */
  public function generatePdfFromNode(NodeInterface $node) {
    // Define the name of the pdf.
    $file_name = 'emailed_pdfs/' . $node->label() . '.pdf';
    // Generate the pdf.
    $print_engine = $this->pluginManagerEntityPrintPrintEngine->createSelectedInstance('pdf');
    $file_path = $this->entityPrintPrintBuilder->savePrintable([$node], $print_engine, 'public', $file_name);
    if ($file_path) {
      return [
        'uri' => $file_path,
        'print_engine' => $print_engine,
      ];
    }
    return [];
  }

  /**
   * Send the mail with the given file as attachment.
   */
  public function sendMail(string $file_uri, PrintEngineInterface $print_engine) {
    $module = 'my_module';
    $key = 'node_pdf_mail';
    $to_mail = 'test@test.com';
    // Create the file attachment.
    $attachment = [
      'filecontent' => $print_engine->getBlob(),
      'filepath' => $file_uri,
      'filemime' => 'application/pdf',
    ];
    $params['attachments'] = $attachment;
    $params['message'] = 'Mail subject';
    $params['subject'] = 'Mail body';
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    // Send the mail.
    $result = $this->pluginManagerMail->mail($module, $key, $to_mail, $langcode, $params, NULL, TRUE);
    return $result;
  }

}
