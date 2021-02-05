<?php
/**
 * @file
 * Contains \Drupal\hallazgos_alta\Form\HallazgosAltaForm
 */
namespace Drupal\hallazgos_alta\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a RSVP Email form.
 */
class HallazgosAltaForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hallazgos_alta_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    $form['nombre'] = array(
      '#type' => 'textfield',
      '#title' => 'Nombre del hallazgo/vulnerabilidad.',
      '#required' => TRUE,
      '#size' => 1000,
    );
    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#description' => t('Descripción de la vulnerabilidad/hallazgo.'),
      '#required' => TRUE,
    );
    $form['solution'] = array(
      '#type' => 'textarea',
      '#title' => t('Solución/Recomendación'),
      '#description' => t('Solución o recomendación a la vulnerabilidad/hallazgo.'),
      '#required' => TRUE,
    );
    $form['references'] = array(
      '#title' => t('References'),
      '#type' => 'textarea',
      '#required' => TRUE,
    );
    //aqui falta el catalogo para el impacto
    $form['cvss_vector'] = array(
      '#title' => t('Vector'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 108,
      '#size' => 1000,
    );
    $form['cvss_enlace'] = array(
      '#title' => t('Enlace'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 159,
      '#size' => 1000,
    );
    $form['resumen_ejecutivo'] = array(
      '#title' => t('Resumen Ejecutivo'),
      '#type' => 'textarea',
      '#description' => t('Descripción de alto nivel, es decir, resumen ejecutivo.'),
      '#required' => TRUE,
    );
    $form['recomendation'] = array(
      '#title' => t('Recomendación general'),
      '#type' => 'textarea',
      '#required' => TRUE,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Dar de alta'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
	  // insertando en la base de datos
	  $connection = \Drupal::service('database');
	  $result = $connection->insert('hallazgos')
		  ->fields(array(
			  'nombre_hallazgo' => $form_state->getValue('nombre'),
			  'descripcion_hallazgo' => $form_state->getValue('description'),
			  'solucion_recomendacion_hallazgo' => $form_state->getValue('solution'),
			  'referencias_hallazgo' => $form_state->getValue('references'),
			  'resumen_ejecutivo' => $form_state->getValue('resumen_ejecutivo'),
			  'recomendacion_general' => $form_state->getValue('recomendation'),
		  ))
		  ->execute();
	  $messenger_service = \Drupal::service('messenger');
	  $messenger_service->addMessage(t('Hallazgo insertado en la base de datos'));
  }
}
