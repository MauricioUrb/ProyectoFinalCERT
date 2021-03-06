<?php
/**
 * @file
 * Contains \Drupal\eliminar_hallazgos\Form\EliminarHallazgosForm
 */
namespace Drupal\eliminar_hallazgos\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

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

    $url = Url::fromRoute('hallazgos_show.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $idh;
	  // se hace la conexion a la base de datos
	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	  $connection = \Drupal\Core\Database\Database::getConnection();
	  // se elimina un registro tabla hallazgos
	  $update = $connection->delete('hallazgos')
		  //Se agregan condiciones
		  //Donde el id sea
		  ->condition('id_hallazgo', $idh)
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
	  //regresar a la db default
	  \Drupal\Core\Database\Database::setActiveConnection();
  }
}
