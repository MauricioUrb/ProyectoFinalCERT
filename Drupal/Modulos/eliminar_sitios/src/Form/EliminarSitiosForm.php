<?php
/**
 * @file
 * Contains \Drupal\eliminar_sitios\Form\EliminarSitiosForm
 */
namespace Drupal\eliminar_sitios\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a RSVP Email form.
 */
class EliminarSitiosForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eliminar_sitios_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id_s = NULL) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    global $ids;
    $ids = $id_s;

    $txt = '';
//    $txt .= '<br />';
    $txt .= 'Confirmar eliminación de registro';
    $txt .= '<br />';
    $txt .= '<br />';

    $form['txt']['#markup'] = $txt;

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Aceptar'),
    );

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $ids;
	  // se hace la conexion a la base de datos
	  $connection = \Drupal::service('database');
	  // se elimina un registro tabla hallazgos
	  $update = $connection->delete('sitios')
		  //Se agregan condiciones
		  //Donde el id sea
		  ->condition('id_sitios', $ids)
		  // ejecutamos el query
	  	  ->execute();
	  // mostramos el mensaje
          if($update!=FALSE){
                  $messenger_service = \Drupal::service('messenger');
                  $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
          } else {
                  $messenger_service = \Drupal::service('messenger');
                  $messenger_service->addMessage(t('No existe el registro'));
          }
  }
}
