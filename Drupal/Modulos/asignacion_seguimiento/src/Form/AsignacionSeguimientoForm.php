<?php
/*
 * @file
 * Contains \Drupal\asignacion_seguimiento\Form\AsignacionSeguimientoForm
 */
namespace Drupal\asignacion_seguimiento\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
+ Descripción: Formulario para asignar una revisión de seguimiento a pentesters notificándolos por correo electrónico.
*/
class AsignacionSeguimientoForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'asignacion_seguimiento_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: id de revisión | Tipo: int, Default: NULL |
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    //Revisar si no hay un seguimiento en proceso
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r',array('id_revision'));
    $select->condition('id_seguimiento',$rev_id);
    $rev_seg = $select->execute()->fetchCol();
    //Se revisa si tiene revisiones de seguimiento
    $seguimiento = FALSE;
    if(sizeof($rev_seg)){
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->addExpression('MAX(id_revision)','revisiones');
      $select->condition('id_seguimiento',$rev_id);
      $max_seg = $select->execute()->fetchCol();
      //estatus_revision_seguimiento
      $select = Database::getConnection()->select('actividad', 'a');
      $select->addExpression('MAX(id_estatus)','actividad');
      $select->condition('id_revision',$max_seg[0]);
      $estatusS = $select->execute()->fetchCol();
      if($estatusS[0] < 4){$seguimiento = TRUE;}
    }
    Database::setActiveConnection();
    $current_user_roles = \Drupal::currentUser()->getRoles();
    $grupo = TRUE;
    if(in_array('sistemas', $current_user_roles) || in_array('auditoria', $current_user_roles)){$grupo = FALSE;}
    if(!in_array('coordinador de revisiones', $current_user_roles) || $grupo || $estatus[0] != 4){
      return array('#markup' => "No tienes permiso para ver este formulario.",);
    }elseif ($seguimiento) {
      return array('#markup' => "Esta revisión ya está en seguimiento.",);
    }
    global $no_rev;
    $no_rev = $rev_id;
    $form['id'] = array(
      '#type' => 'item',
      '#title' => t('ID de revisón a mandar a seguimiento:'),
      '#markup' => $rev_id,
    );
    //Se obtienen los datos correspondientes a esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('tipo_revision'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    if($result[0]){$tipo = 'Circular';}else{$tipo = 'Oficio';}
    $form['tipo'] = array(
      '#type' => 'item',
      '#title' => 'Tipo:',
      '#markup' => $tipo,
    );

    //Se obtienen los pentesters
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision', $rev_id);
    $select->condition('id_usuario', \Drupal::currentUser()->id(), '<>');
    $usuarios_rev = $select->execute()->fetchCol();

    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $pentesters = $select->execute()->fetchCol();
    $nombres = '';
    foreach ($pentesters as $pentester) {$nombres .= $pentester.', ';}
    $nombres = substr($nombres, 0, -2);
    
    $form['nombres'] = array(
      '#type' => 'item',
      '#title' => 'Pentesters asignados:',
      '#markup' => $nombres,
    );
    //Se obtienen los datos de los hallazgos de esta revisión
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios', 's', 'r.id_sitio = s.id_sitio');
    $select->fields('s', array('url_sitio'));
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_id);
    $ids = $select->execute();
    foreach ($ids as $id) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('descripcion_hall_rev'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->fields('r', array('cvss_hallazgos'));
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$id->id_rev_sitio);
      $datHall = $select->execute();
      $form[$id->id_rev_sitio] = array(
          '#type' => 'item',
          '#title' => 'Sitio:',
          '#markup' => $id->url_sitio,
        );
      foreach ($datHall as $hallazgo) {
        //Nombre del hallazgo
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->condition('id_hallazgo',$hallazgo->id_hallazgo);
        $nombreHallazgo = $select->execute()->fetchCol();
        //Se imprime en pantalla los datos correspondiente al sitio-hallazgo
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo] = array(
          '#type' => 'fieldset',
          '#collapsible' => TRUE, 
          '#collapsed' => FALSE,
        );
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['hallazgo'] = array(
          '#type' => 'item',
          '#title' => 'Hallazgo:',
          '#markup' => $nombreHallazgo[0],
        );
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['descripcion'] = array(
          '#type' => 'item',
          '#title' => 'Descripción:',
          '#markup' => $hallazgo->descripcion_hall_rev,
        );$form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['impacto'] = array(
          '#type' => 'item',
          '#title' => 'Impacto',
          '#markup' => $hallazgo->impacto_hall_rev,
        );
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['cvss'] = array(
          '#type' => 'item',
          '#title' => 'CVSS:',
          '#markup' => $hallazgo->cvss_hallazgos,
        );
      }
    }

    Database::setActiveConnection();

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

    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Asignar revisión'),
    );
    $url = Url::fromRoute('revisiones_aprobadas.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['test'] = array('#markup' => render($project_link));
    return $form;
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $no_rev;
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
    $params['context']['message'] = 'Se le ha asignado una nueva revisión de seguimiento.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}

    //uid_usuarios
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->fields('r', array('uid'));
    $consulta->condition('name',$nombresPentester, 'IN');
    $uid_usuarios = $consulta->execute()->fetchCol();
    
    //Insercion en la BD
    $fecha = getdate();
    $hoy = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //Nuevo id_revision
    $consulta = Database::getConnection()->select('revisiones', 'r');
    $consulta->addExpression('MAX(id_revision)','revisiones');
    $resultado = $consulta->execute()->fetchCol();
    $id_revisiones = $resultado[0] + 1;
    //Tipo revision
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('tipo_revision'));
    $select->condition('id_revision',$no_rev);
    $results = $select->execute()->fetchCol();
    if($results[0]){$tipo = 1;}else{$tipo = 0;}
    //Obtener número de revision de seguimiento
    $consulta = Database::getConnection()->select('revisiones', 'r');
    $consulta->addExpression('COUNT(id_seguimiento)','revisiones');
    $consulta->condition('id_seguimiento',$no_rev);
    $resultado = $consulta->execute()->fetchCol();
    $num_seg = $resultado[0] + 1;
    //revisiones
    $result = $connection->insert('revisiones')
      ->fields(array(
        'id_revision' => $id_revisiones,
        'tipo_revision' => $tipo,
        'seguimiento' => $num_seg,
        'id_seguimiento' => $no_rev,
      ))->execute();
    //actividad
    $result = $connection->insert('actividad')
      ->fields(array(
        'id_revision' => $id_revisiones,
        'id_estatus' => 1,
        'fecha' => $hoy,
      ))->execute();
    //revisiones_asignadas
    foreach ($uid_usuarios as $pentester) {
      $result = $connection->insert('revisiones_asignadas')
        ->fields(array(
          'id_revision' => $id_revisiones,
          'id_usuario' => $pentester,
        ))->execute();
    }
    if(!in_array(\Drupal::currentUser()->id(), $uid_usuarios)){
      $result = $connection->insert('revisiones_asignadas')
        ->fields(array(
          'id_revision' => $id_revisiones,
          'id_usuario' => \Drupal::currentUser()->id(),
        ))->execute();
    }
    //sitios
    $consulta = Database::getConnection()->select('revisiones_sitios', 'r');
    $consulta->addExpression('MAX(id_rev_sitio)','revisiones_sitios');
    $resultado = $consulta->execute()->fetchCol();
    $id_rev_sitio = $resultado[0] + 1;
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $id_sitios = $select->execute()->fetchCol();
    foreach ($id_sitios as $sitios) {
      $result = $connection->insert('revisiones_sitios')
        ->fields(array(
          'id_rev_sitio' => $id_rev_sitio,
          'id_revision' => $id_revisiones,
          'id_sitio' => $sitios,
        ))->execute();
      $id_rev_sitio++;
    }
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}
