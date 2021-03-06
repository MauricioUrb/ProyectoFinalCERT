<?php
/**
 * @file
 * Contains \Drupal\eliminar_sitios\Form\EliminarSitiosForm
 */
namespace Drupal\eliminar_sitios\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

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

    $url = Url::fromRoute('sitios_show.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
//    $project_link['#attributes'] = array('class' => array('button'));
    $project_link['#attributes'] = array('class' => array('button', 'button-action', 'button--primary', 'button--small'));
    $form['Cancelar'] = array('#markup' => render($project_link),);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $ids;
	  //se hace la conexion a la base de datos
	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	  $connection = \Drupal\Core\Database\Database::getConnection();
	  $elimina = $connection->delete('dependencias_sitios')
		  //se agrega la condicion id_sitio
		  ->condition('id_sitio', $ids)
		  ->execute();
	  $elimina = $connection->delete('ip_sitios')
		  ->condition('id_sitio', $ids)
		  ->execute();
	  // se elimina un registro tabla hallazgos
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
