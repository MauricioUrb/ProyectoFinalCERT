<?php
/**
 * @file
 * Contains \Drupal\editar_hallazgos\Form\EditarHallazgosForm
 */
namespace Drupal\editar_hallazgos\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class EditarHallazgosForm extends FormBase {

  public function getFormId() {
    return 'editar_hallazgos_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id_h = NULL) {
    //Se revisa que el sitio esté activo para poder editarlo
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();
    $select = Database::getConnection()->select('hallazgos', 'h');
    $select->fields('h', array('activo'));
    $select->condition('id_hallazgo',$id_h);
    $results = $select->execute()->fetchCol();
    \Drupal\Core\Database\Database::setActiveConnection();
    if(!$results[0]){
      return array('#markup' => "No puedes modificar este registro. Contacta con el administrador.",);
    }

    
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('pentester', \Drupal::currentUser()->getRoles())){
      global $varh;
      $varh = $id_h;
      //conectar a la otra db
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      //Se selecciona la tabla
      $select = Database::getConnection()->select('hallazgos', 'h');
      //Se especifican las columnas a leer
      $select->fields('h', array('id_hallazgo'))
             ->fields('h', array('nombre_hallazgo_vulnerabilidad'))
             ->fields('h', array('descripcion_hallazgo'))
             ->fields('h', array('solucion_recomendacion_halazgo'))
             ->fields('h', array('referencias_hallazgo'))
             ->fields('h', array('recomendacion_general_hallazgo'))
             ->fields('h', array('nivel_cvss'))
             ->fields('h', array('vector_cvss'))
             ->fields('h', array('enlace_cvss'))
             ->fields('h', array('r_ejecutivo_hallazgo'));
      $select->condition('id_hallazgo', $id_h);
      //Se realiza la consulta
      $results = $select->execute();
      //regresar a la default
      \Drupal\Core\Database\Database::setActiveConnection();
      $txt = '';
      //se recorren los resultados para después imprimirlos
      foreach ($results as $result){

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
    else{
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
          global $varh;
	  //se hace la conexion a la otra db
 	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
          $connection = \Drupal\Core\Database\Database::getConnection();

          // se realiza el update en la tabla hallazgos
          $update = $connection->update('hallazgos')
                  // Se agregan actualizan todos los campos.
                  ->fields(array(
                          'nombre_hallazgo_vulnerabilidad' => $form_state->getValue('nombre'),
                          'descripcion_hallazgo' => $form_state->getValue('description'),
                          'solucion_recomendacion_halazgo' => $form_state->getValue('solution'),
                          'referencias_hallazgo' => $form_state->getValue('references'),
                          'vector_cvss' => $form_state->getValue('cvss_vector'),
                          'enlace_cvss' => $form_state->getValue('cvss_enlace'),
                          'r_ejecutivo_hallazgo' => $form_state->getValue('resumen_ejecutivo'),
                          'recomendacion_general_hallazgo' => $form_state->getValue('recomendation'),
                  ))
                  //Se agregan condiciones
                  ->condition('id_hallazgo', $varh)
                  // ejecutamos el query
                  ->execute();
          // mostramos el mensaje de que se actualizo
          $messenger_service = \Drupal::service('messenger');
          $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
	  //regresar a la default
	  \Drupal\Core\Database\Database::setActiveConnection();
	  $form_state->setRedirectUrl(Url::fromRoute('hallazgos_show.content'));
  }
}
