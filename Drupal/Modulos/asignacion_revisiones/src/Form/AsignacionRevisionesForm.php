<?php
/*
 * @file
 * Contains \Drupal\asignacion_revisiones\Form\AsignacionRevisionesForm
 */
namespace Drupal\asignacion_revisiones\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;

/*
 *
 */
class AsignacionRevisionesForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'asignacion_revisiones_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $current_user_roles = \Drupal::currentUser()->getRoles();
    if (!in_array('coordinador de revisiones', $current_user_roles)){
      return array('#markup' => "No tienes permiso para ver este formulario.",);
    }
    //Tipo de revision
    $active = array(0 => t('Oficio'), 1 => t('Circular'));
    $form['tipo'] = array(
      '#type' => 'radios',
      '#title' => t('Tipo de revisión'),
      '#default_value' => isset($node->active) ? $node->active : 0,
      '#options' => $active,
    );

    //Coneccion a la BD
    $select = Database::getConnection()->select('users_field_data', 'ud');
    $select->join('user__roles', 'ur', 'ud.uid = ur.entity_id');
    $select->fields('ud', array('name'));
    $select->condition('ur.roles_target_id','pentester');
    $results = $select->execute()->fetchCol();

    //Se crean las opciones para los revisores
    $form['revisores'] = array(
      '#title' => t('Asignar reviores:'),
      '#type' => 'checkboxes',
      '#options' => $results,
      '#required' => TRUE,
    );

    //Sitios
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $consulta = Database::getConnection()->select('sitios', 's');
    $consulta->fields('s', array('url_sitio'));
    $sitios = $consulta->execute()->fetchCol();
    Database::setActiveConnection();
    
    $form['sitios'] = array(
      '#title' => t('Sitios:'),
      '#type' => 'checkboxes',
      '#options' => $sitios,
      '#required' => TRUE,
    );

    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Asignar revisión'),
    );
    return $form;
  }
  /*
   * (@inheritdoc)
   * Validacion de los datos ingresados
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //https://www.drupal.org/docs/drupal-apis/form-api/introduction-to-form-api
    //Conteo de sitios seleccionados
    $tmp = '';
    $test = $form_state->getValue(['sitios']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    /*
    //Obtencion del valor de las opciones marcadas
    $tmp = 0;
    foreach($valores as $pos){
      $contenido[$tmp] = $form['sitios']['#options'][$pos];
      $tmp++;
    }*/
    //Validacion de tipo de revision
    $cadena = 'En revision de tipo Oficio solo puedes escoger un sitio para revision.';
    if($form_state->getValue(['tipo']) == 0 && sizeof($valores) != 1){
      $form_state->setErrorByName('sitios',$cadena);
    }
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Buscar nombres de los pentester asignados
    $tmp = '';
    $test = $form_state->getValue(['revisores']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    $tmp = 0;
    foreach($valores as $pos){
      $nombresPentester[$tmp] = $form['revisores']['#options'][$pos];
      $tmp++;
    }
    //Buscar sus correos en la BD
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->fields('r', array('mail'));
    $consulta->condition('name',$nombresPentester, 'IN');
    $correos = $consulta->execute()->fetchCol();

    //Se manda correo
    $to = "";
    foreach($correos as $mail){
      $to .= $mail.',';
    }
    $to = substr($to, 0, -1);
    $mensaje = 'Revisión enviada.';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Asignación de revisión";
    $params['context']['message'] = 'Se le ha asignado una nueva revisión.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}

    //id_revisiones
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $consulta = Database::getConnection()->select('revisiones', 'r');
    $consulta->addExpression('MAX(id_revision)','revisiones');
    $resultado = $consulta->execute()->fetchCol();
    $id_revisiones = $resultado[0] + 1;
    Database::setActiveConnection();
    //uid_usuarios
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->fields('r', array('uid'));
    $consulta->condition('name',$nombresPentester, 'IN');
    $uid_usuarios = $consulta->execute()->fetchCol();
    //Obteniendo sitios seleccionados
    $tmp = '';
    $test = $form_state->getValue(['sitios']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    $tmp = 0;
    foreach($valores as $pos){
      $url_sitios[$tmp] = $form['sitios']['#options'][$pos];
      $tmp++;
    }
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //id_sitios
    $consulta = Database::getConnection()->select('sitios', 'r');
    $consulta->fields('r', array('id_sitio'));
    $consulta->condition('url_sitio',$url_sitios, 'IN');
    $id_sitios = $consulta->execute()->fetchCol();
    Database::setActiveConnection();
    //Fecha
    $tmp = getdate();
    $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
    //Otros datos
    $tipo_revision = $form_state->getValue(['tipo']);
    $estatus_revision = 1;
    //id_revisiones,uid_usuarios[],id_sitios[]
    //Insercion en la BD
    //revisiones
    //Database::setActiveConnection('drupaldb_segundo');
    //$connection = Database::getConnection();
    //$coneccion = \Drupal::service('database');
    $result = $connection->insert('revisiones')
      ->fields(array(
        'id_revision' => $id_revisiones,
        'tipo_revision' => $tipo_revision,
        'id_estatus'=> $estatus_revision,
        'fecha_inicio_revision' => $fecha,
        'fecha_fin_revision' => NULL,
      ))->execute();
    //revisiones_asignadas
    foreach ($uid_usuarios as $pentester) {
      $result = $connection->insert('revisiones_asignadas')
        ->fields(array(
          'id_revision' => $id_revisiones,
          'id_usuario' => $pentester,
        ))->execute();
    }
    /*/revisiones_sitios
    foreach ($id_sitios as $sitios) {
      $result = $connection->insert('revisiones_sitios')
        ->fields(array(
          'id_revisiones' => $id_revisiones,
          'id_sitios' => $sitio,
          'id_revisiones_hallazgos' => NULL,
        ))->execute();
    }*/
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  }
}