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
      //Se revisa que el sitio esté activo para poder editarlo
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      $select = Database::getConnection()->select('sitios', 'h');
      $select->fields('h', array('activo'));
      $select->condition('id_sitio',$id_s);
      $results = $select->execute()->fetchCol();
      \Drupal\Core\Database\Database::setActiveConnection();
      if(!$results[0]){
        return array('#markup' => "Este registro no está disponible. Contacta con el administrador.",);
      }
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles())){
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
    else{
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $ids;
	  $messenger_service = \Drupal::service('messenger');
    //se hace la conexion a la base de datos
	  \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	  $connection = \Drupal\Core\Database\Database::getConnection();
    //Nombre del sitio
    $consulta = Database::getConnection()->select('sitios', 's');
    $consulta->fields('s', array('url_sitio'));
    $consulta->condition('id_sitio', $ids);
    $urlSitio = $consulta->execute()->fetchCol();
    //Revisiones actualmente activas
    $consulta = Database::getConnection()->select('actividad', 'a');
    $consulta->fields('a', array('id_revision'));
    $consulta->groupBy('id_revision');
    $consulta->having('MAX(id_estatus) < 4');
    $revisiones = $consulta->execute()->fetchCol();
    // se desactiva el registro en la tabla hallazgos
    $update = $connection->update('sitios')
      ->fields(array('activo' => 0))
      //Se agregan condiciones
      //Donde el id sea
      ->condition('id_sitio', $ids)
      // ejecutamos el query
        ->execute();
	  //regresar a la default
	  \Drupal\Core\Database\Database::setActiveConnection();
	  //Si no se pudo actualizar, no se realiza ninguna otra acción
    if(!$update){
        $mensaje = "No existe el registro.";
        $messenger_service->addMessage($mensaje);
        $form_state->setRedirectUrl(Url::fromRoute('sitios_show.content'));
    }
    $mensaje = "Sitio eliminado.";
    //Notificacion de correo de elminacion de sitio a todos los usuarios
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
    $params['context']['message'] = 'Se ha eliminado el sitio "'.$urlSitio[0].'". Si tiene alguna revisión en proceso en el que se haya asignado este sitio, este será eliminado.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$badMail = TRUE;}

    //se hace la conexion a la base de datos
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();
    //Revisiones activas que han asignado este sitio
    if(sizeof($revisiones)){
      $consulta = Database::getConnection()->select('revisiones_sitios', 'r');
      $consulta->fields('r', array('id_revision'));
      $consulta->condition('id_sitio', $ids);
      $consulta->condition('id_revision', $revisiones, 'IN');
      $revDel = $consulta->execute()->fetchCol();
      //Notificacion a los pertenecientes de las revisiones que fueron eliminadas
      foreach($revDel as $rev){
        \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
        $connection = \Drupal\Core\Database\Database::getConnection();
        //Obtención id usuarios
        $consulta = Database::getConnection()->select('revisiones_asignadas', 'r');
        $consulta->fields('r',array('id_usuario'));
        $consulta->condition('id_revision',$rev);
        $idU = $consulta->execute()->fetchCol();
        //Eliminacion de la revisión
        $tmp = getdate();
        $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
        $update = $connection->insert('actividad')
          ->fields(array(
            'id_revision' => $rev,
            'id_estatus' => 5,
            'fecha' => $fecha,
          ))
          ->execute();
        \Drupal\Core\Database\Database::setActiveConnection();
        //Obtención de mails
        $consulta = Database::getConnection()->select('users_field_data', 'r');
        $consulta->fields('r', array('mail'));
        $consulta->condition('uid',$idU,'IN');
        $correos = $consulta->execute()->fetchCol();
        $to = "";
        foreach($correos as $mail){ $to .= $mail.','; }
        $to = substr($to, 0, -1);
        $params['context']['subject'] = "Notificación de eliminación de revision por eliminación de sitio.";
        $params['context']['message'] = 'Se ha eliminado la revisión con ID '.$rev." debido a la eliminación del sitio ".$urlSitio[0].". Favor de ponerse en contacto con el coordinador de la revisión.";
        //$to = 'mauricio@dominio.com,angel@dominio.com';
        $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
        if(!$email){$badMail = TRUE;}
      }
    }
    //regresar a la default
    \Drupal\Core\Database\Database::setActiveConnection();
    if($badMail){$mensaje .= " Ocurrió algún error y no se ha podido enviar el correo de notificación a algunos usuarios. Favor de contactar con el administrador.";}

    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('sitios_show.content'));
  }
}
