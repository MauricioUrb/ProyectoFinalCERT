<?php
/*
 * @file
 * Contains \Drupal\comentar_revision\Form\ComentarRevisionForm
 */
namespace Drupal\comentar_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/*
+ Descripción: Formulario para mandar comentarios a los pentesters sobre una revisión y regresar el estado de ésta a "En proceso".
*/
class ComentarRevisionForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'comentar_revision_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: id de revisión | Tipo: int, Default: NULL |
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    $current_user_roles = \Drupal::currentUser()->getRoles();
    if (!in_array(\Drupal::currentUser()->id(), $results) || !in_array('coordinador de revisiones', $current_user_roles) || $estatus[0] != 3){
      return array('#markup' => "No tienes permiso para ver esta página.",);
    }
    global $no_rev;
    global $seguimiento;
    $no_rev = $rev_id;
    $form['text'] = array(
      '#type' => 'item',
      '#title' => 'Comentar revisión:',
      '#markup' => 'ID revisión: '. $rev_id,
    );
    $form['comentario'] = array(
      '#type' => 'textarea',
      '#title' => t('Comentario:'),
      '#required' => TRUE,
    );
    $form['aprobar'] = array(
      '#type' => 'submit',
      '#value' => t('Comentar'),
    );
    //Se busca si es revisión de seguimiento o no
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('seguimiento'));
    $select->condition('id_revision',$rev_id);
    $seg = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $seguimiento = $seg;
    if($seg[0] != 0){
      $url = Url::fromRoute('informacion_seguimiento.content', array('rev_id' => $rev_id));
    }else{
      $url = Url::fromRoute('informacion_revision.content', array('rev_id' => $rev_id));
    }
    $cancelar = Link::fromTextAndUrl('Cancelar', $url);
    $cancelar = $cancelar->toRenderable();
    $cancelar['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($cancelar),);
    return $form;
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $no_rev;
    global $seguimiento;
    $mensaje = 'Comentario enviado a los pentesters.';
    //Consulta de correos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
    //$select->condition('id_usuario',\Drupal::currentUser()->id(),'<>');
    $id_pentester = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->fields('u', array('mail'));
    $select->condition('uid',$id_pentester, 'IN');
    $correos = $select->execute()->fetchCol();
    //Envio de correo
    $to = "";
    foreach($correos as $mail){ $to .= $mail.',';}
    $to = substr($to, 0, -1);
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Asignación de revisión";
    $params['context']['message'] = 'El Coordinador de revisiones ha realizado un comentario en la revision #'. $no_rev.".\n\n".$form_state->getValue(['comentario']);
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje .= " Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $borrar = $connection->delete('actividad')
      ->condition('id_revision', $no_rev)
      ->condition('id_estatus', 3)
      ->execute();
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}