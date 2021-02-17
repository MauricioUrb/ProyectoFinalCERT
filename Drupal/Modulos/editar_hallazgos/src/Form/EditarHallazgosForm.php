<?php
/**
 * @file
 * Contains \Drupal\editar_hallazgos\Form\EditarHallazgosForm
 */
namespace Drupal\editar_hallazgos\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a RSVP Email form.
 */
class EditarHallazgosForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'editar_hallazgos_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    //Coneccion a la BD
    $node = \Drupal::routeMatch()->getParameter('node');
    //Se selecciona la tabla en modo lectura
    $select = Database::getConnection()->select('hallazgos', 'h');
    //Se especifican las columnas a leer
    $select->fields('h', array('id_hallazgos'))
           ->fields('h', array('nombre_hallazgo_vulnerabilidad'))
           ->fields('h', array('descripcion_hallazgo'))
           ->fields('h', array('solucion_recomendacion_halazgo'))
           ->fields('h', array('referencias_hallazgo'))
           ->fields('h', array('recomendacion_general_hallazgo'))
           ->fields('h', array('nivel_cvss'))
           ->fields('h', array('vector_cvss'))
           ->fields('h', array('enlace_cvss'))
           ->fields('h', array('r_ejecutivo_hallazgo'));
    $select->condition('id_hallazgos', 3);
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){
/*      $txt .= ' ' . $result->id_hallazgos;
      $txt .= ' ' . $result->descripcion_hallazgo;
      $txt .= ' ' . $result->referencias_hallazgo;
      $txt .= ' ' . $result->nivel_cvss;
      $txt .= '<br />';*/


    $form['nombre'] = array(
      '#type' => 'textfield',
      '#title' => 'Nombre del hallazgo/vulnerabilidad.',
      '#required' => TRUE,
      '#size' => 1000,
      '#default_value' => $result->nombre_hallazgo_vulnerabilidad,
    );
    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#description' => t('Descripción de la vulnerabilidad/hallazgo.'),
      '#required' => TRUE,
      '#default_value' => $result->descripcion_hallazgo,
    );
    $form['solution'] = array(
      '#type' => 'textarea',
      '#title' => t('Solución/Recomendación'),
      '#description' => t('Solución o recomendación a la vulnerabilidad/hallazgo.'),
      '#required' => TRUE,
      '#default_value' => $result->solucion_recomendacion_halazgo,
    );
    $form['references'] = array(
      '#title' => t('References'),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $result->referencias_hallazgo,
    );
    //aqui falta el catalogo para el impacto
    $form['cvss_vector'] = array(
      '#title' => t('Vector'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 108,
      '#size' => 1000,
      '#default_value' => $result->vector_cvss,
    );
    $form['cvss_enlace'] = array(
      '#title' => t('Enlace'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 159,
      '#size' => 1000,
      '#default_value' => $result->enlace_cvss,
    );
    $form['resumen_ejecutivo'] = array(
      '#title' => t('Resumen Ejecutivo'),
      '#type' => 'textarea',
      '#description' => t('Descripción de alto nivel, es decir, resumen ejecutivo.'),
      '#required' => TRUE,
      '#default_value' => $result->r_ejecutivo_hallazgo,
    );
    $form['recomendation'] = array(
      '#title' => t('Recomendación general'),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $result->recomendacion_general_hallazgo,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Actualizar'),
    );
    }

//    $form['txt']['#markup'] = $txt;

    return $form;
  }

  /**
   * {@inheritdoc}
   * Se hace el insert a la bases de datos
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
	  // se hace la conexion a la base de datos
	  $connection = \Drupal::service('database');
	  // se realiza el update en la tabla hallazgos
	  $update = $connection->update('hallazgos')
		  // Se agregan actualizan todos los campos.
		  ->fields(array(
			  'nombre_hallazgo_vulnerabilidad' => $form_state->getValue('nombre'),
			  'descripcion_hallazgo' => $form_state->getValue('description'),
			  'solucion_recomendacion_halazgo' => $form_state->getValue('solution'),
			  'referencias_hallazgo' => $form_state->getValue('references'),
			  'r_ejecutivo_hallazgo' => $form_state->getValue('resumen_ejecutivo'),
			  'recomendacion_general_hallazgo' => $form_state->getValue('recomendation'),
		  ))
		  //Se agregan condiciones
		  ->condition('id_hallazgos', 3)
		  // ejecutamos el query
	  	  ->execute();
	  // mostramos el mensaje de que se inserto
	  $messenger_service = \Drupal::service('messenger');
	  $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
  }
}
