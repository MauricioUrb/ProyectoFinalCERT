<?php
/**
 * @file
 * Contains \Drupal\eliminar_imagen\Form\EliminarImagenForm
 */
namespace Drupal\eliminar_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a RSVP Email form.
 */
class EliminarImagenForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eliminar_imagen_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL) {
    global $id;
    $id = $fid;

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

    $url = Url::fromRoute('mostrar_imagen.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $id;
	  //se hace la conexion a la base de datos
    $connection = \Drupal::service('database');
	  //se elimina un regitro de la tabla file_managed
	  $elimina = $connection->delete('file_managed')
		  //se agrega la condicion id_sitio
		  ->condition('fid', $id)
		  ->execute();
	  // mostramos el mensaje
    if($elimina!=FALSE){
      $messenger_service = \Drupal::service('messenger');
      $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
    } else {
      $messenger_service = \Drupal::service('messenger');
      $messenger_service->addMessage(t('No existe el registro'));
    }
  }
}
