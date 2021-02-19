<?php
/**
 * @file
 * Contains \Drupal\eliminar_hallazgos\Form\EliminarHallazgosForm
 */
namespace Drupal\eliminar_hallazgos\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a RSVP Email form.
 */
class EliminarHallazgosForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eliminar_hallazgos_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_h = NULL) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    global $idh;
    $idh = $id_h;
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
	  global $idh;
	  // se hace la conexion a la base de datos
	  $connection = \Drupal::service('database');
	  // se elimina un registro tabla hallazgos
	  $update = $connection->delete('hallazgos')
		  //Se agregan condiciones
		  //Donde el id sea
		  ->condition('id_hallazgos', $idh)
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
