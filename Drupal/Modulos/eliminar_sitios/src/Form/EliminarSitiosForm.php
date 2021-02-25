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
	  //se hace la conexion a la base de datos
	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	  $connection = \Drupal\Core\Database\Database::getConnection();
    //eliminamos primero la referencia del sitio en la tabla dependencias_sitios
	  $elimina = $connection->delete('dependencias_sitios')
		  //se agrega la condicion id_sitio
		  ->condition('id_sitio', $ids)
		  ->execute();
    //despues eliminamos la referencia del sitio en la tabla ip_sitios
	  $elimina = $connection->delete('ip_sitios')
		  ->condition('id_sitio', $ids)
		  ->execute();
	  //por ultimo se elimina el registro en la tabla sitios
	  $update = $connection->delete('sitios')
		  //Se agregan condiciones
		  //Donde el id sea
		  ->condition('id_sitio', $ids)
		  // ejecutamos el query
	  	  ->execute();
	  //regresar a la default
	  \Drupal\Core\Database\Database::setActiveConnection();
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
