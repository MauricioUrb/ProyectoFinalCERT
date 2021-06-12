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
use Drupal\Core\Mail\MailInterface;

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
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles())){ 
      //Se revisa que el sitio esté activo para poder editarlo
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('activo'));
      $select->condition('id_hallazgo',$id_h);
      $results = $select->execute()->fetchCol();
      \Drupal\Core\Database\Database::setActiveConnection();
      if(!$results[0]){
        return array('#markup' => "Este registro no está disponible. Contacta con el administrador.",);
      }
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
    else{
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $idh;
    $messenger_service = \Drupal::service('messenger');
	  // se hace la conexion a la base de datos
	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	  $connection = \Drupal\Core\Database\Database::getConnection();
    //Nombre del hallazgo
    $consulta = Database::getConnection()->select('hallazgos', 'h');
    $consulta->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    $consulta->condition('id_hallazgo', $idh);
    $nombreHallazgo = $consulta->execute()->fetchCol();
    //Revisiones actualmente activas
    $consulta = Database::getConnection()->select('actividad', 'a');
    $consulta->fields('a', array('id_revision'));
    $consulta->groupBy('id_revision');
    $consulta->having('MAX(id_estatus) < 4');
    $revisiones = $consulta->execute()->fetchCol();
    //Revisiones activas que han asignado este hallazgo
    $consulta = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $consulta->join('revisiones_sitios', 'r', 'h.id_rev_sitio = r.id_rev_sitio');
    $consulta->fields('h', array('id_rev_sitio_hall'));
    $consulta->condition('id_hallazgo', $idh);
    $consulta->condition('id_revision', $revisiones, 'IN');
    $irsh = $consulta->execute()->fetchCol();
    // se desactiva el registro en la tabla hallazgos
	  $update = $connection->update('hallazgos')
		  ->fields(array('activo' => 0))
      //Se agregan condiciones
		  //Donde el id sea
		  ->condition('id_hallazgo', $idh)
		  // ejecutamos el query
	  	  ->execute();
	  //regresar a la db default
    \Drupal\Core\Database\Database::setActiveConnection();
    //Si no se pudo actualizar, no se realiza ninguna otra acción
    if(!$update){
      $messenger_service->addMessage(t('No existe el registro.'));
      $form_state->setRedirectUrl(Url::fromRoute('hallazgos_show.content'));
    }
    $mensaje = 'Hallazgo eliminado.';
    
    //Si se desactivo con éxito, se procede a borrar de las revisiones activas y enviar el correo
    if(sizeof($irsh)){
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      $update = $connection->delete('revisiones_hallazgos')
        ->condition('id_rev_sitio_hall',$irsh,'IN')
        ->execute();
      \Drupal\Core\Database\Database::setActiveConnection();
    }
    
    //Notificacion de correo de elminacion de hallazgo
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->join('user__roles','u','r.uid = u.entity_id');
    $consulta->fields('r', array('mail'));
    $consulta->condition('uid',0,'<>');
    $consulta->condition('mail','admin@example.com','<>');
    $consulta->condition('roles_target_id',array('coordinador de revisiones','pentester'),'IN');
    $correos = $consulta->execute()->fetchCol();
    $to = "";
    foreach($correos as $mail){
      $to .= $mail.',';
    }
    $to = substr($to, 0, -1);
    $params['context']['subject'] = "Notificación de eliminación de hallazgo.";
    $params['context']['message'] = 'Se ha eliminado el hallazgo "'.$nombreHallazgo.'". Si tiene alguna revisión en proceso en el que se haya asignado este hallazgo, este será eliminado.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje .= " Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('hallazgos_show.content'));
  }
}
