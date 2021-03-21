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
 *
 */
class AsignacionSeguimientoForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'asignacion_seguimiento_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('seguimiento'));
    $select->condition('id_revision', $rev_id);
    $select->condition('seguimiento', true);
    $seguimiento = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || !in_array('auxiliar', \Drupal::currentUser()->getRoles()) || !in_array('sistemas', \Drupal::currentUser()->getRoles()) || !in_array('auditoria', \Drupal::currentUser()->getRoles()) || $seguimiento[0]){
      return array('#markup' => "No tienes permiso para ver este formulario.",);
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
      //$select->fields('r', array('id_rev_sitio_hall'));
      $select->fields('r', array('descripcion_hall_rev'));
      //$select->fields('r', array('recursos_afectador'));
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
   * (@inheritdoc)
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
    
    //Insercion en la BD}
    $fecha = getdate();
    $hoy = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $update = $connection->update('revisiones')
      ->fields(array(
        'id_estatus' => 5,
        'fecha_inicio_seguimiento' => $hoy,
      ))
      ->condition('id_revision',$no_rev)
      ->execute();
    //revisiones_asignadas
    foreach ($uid_usuarios as $pentester) {
      $result = $connection->insert('revisiones_asignadas')
        ->fields(array(
          'id_revision' => $no_rev,
          'id_usuario' => $pentester,
          'seguimiento' => true,
        ))->execute();
    }
    $result = $connection->insert('revisiones_asignadas')
      ->fields(array(
        'id_revision' => $no_rev,
        'id_usuario' => \Drupal::currentUser()->id(),
        'seguimiento' => true,
      ))->execute();
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}
