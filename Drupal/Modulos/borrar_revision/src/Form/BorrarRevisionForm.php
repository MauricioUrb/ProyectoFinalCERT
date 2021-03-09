<?php
/*
 * @file
 * Contains \Drupal\borrar_revision\Form\BorrarRevisionForm
 */
namespace Drupal\borrar_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
 *
 */
class BorrarRevisionForm extends FormBase{
/*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'borrar_revision_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    $current_user_roles = \Drupal::currentUser()->getRoles();
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array('coordinador de revisiones', $current_user_roles) || !in_array(\Drupal::currentUser()->id(), $results)){
      return array('#markup' => "No tienes permiso para ver este formulario.",);
    }
    global $regresar;
    $regresar = $rev_id;
    $form['rev'] = array(
      '#type' => 'item',
      '#title' => t('ID revisión:'),
      '#markup' => $rev_id,
    );
    //Consulta de la URL del sitio para imprimirlo
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //tipo de revision
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->join('estatus_revisiones',"s","r.id_estatus = s.id_estatus");
    $select->fields('r', array('tipo_revision'));
    $select->fields('s', array('estatus'));
    $select->condition('id_revision',$rev_id);
    $datos = $select->execute();
    foreach ($datos as $dato) {
      if($dato->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
      $form['tipo'] = array(
        '#type' => 'item',
        '#title' => t('Tipo de revisión:'),
        '#markup' => $tipo,
      );
      $form['estatus'] = array(
        '#type' => 'item',
        '#title' => t('Estatus de la revisión:'),
        '#markup' => $dato->estatus,
      );
    }
    
    //sitios
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios',"s","r.id_sitio = s.id_sitio");
    $select->fields('s', array('url_sitio'));
    $select->condition('id_revision',$rev_id);
    //$resultados = $select->execute();
    $activos = $select->execute()->fetchCol();
    $sitios = '';
    foreach ($activos as $activo) {
      $sitios .= $activo . ' , ';
    }
    $sitios = substr($sitios, 0, -3);
    
    //Se obtienen los pentesters y coordindador
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision', $rev_id);
    $usuarios_rev = $select->execute()->fetchCol();
    global $uid;
    $uid = $usuarios_rev;

    Database::setActiveConnection();
    //Pentesters
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles',"r"," r.entity_id = u.uid");
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id','pentester');
    $pentesters = $select->execute()->fetchCol();
    $nombres = '';
    foreach ($pentesters as $pentester) {$nombres .= $pentester.', ';}
    $nombres = substr($nombres, 0, -2);
    //Coordinador
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles',"r"," r.entity_id = u.uid");
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id','coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();
    $form['coordinador'] = array(
      '#type' => 'item',
      '#title' => t('Coordinador:'),
      '#markup' => $coordinador[0],
    );
    $form['pentester'] = array(
      '#type' => 'item',
      '#title' => t('Pentesters asignados:'),
      '#markup' => $nombres,
    );
    $form['activos'] = array(
      '#type' => 'item',
      '#title' => t('Activos'),
      '#markup' => $sitios,
    );
    
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Borrar revisión'),
    );
    $url = Url::fromRoute('revisiones_asignadas.content', array());
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);
    return $form;    
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $regresar;
    global $uid;
    $mensaje = 'Revisión eliminada.';
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles',"r"," r.entity_id = u.uid");
    $select->fields('u', array('mail'));
    $select->condition('uid', $uid, 'IN');
    $select->condition('roles_target_id','pentester');
    $correos = $select->execute()->fetchCol();
    //Se manda correo
    $to = "";
    foreach($correos as $mail){
      $to .= $mail.',';
    }
    $to = substr($to, 0, -1);
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Revisión" . $regresar . "eliminada";
    $params['context']['message'] = 'El coordinador encargado ha eliminado la revisión #' . $regresar . '.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje .= "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}else{$mensaje .= 'Se notificará a los pentesters por correo electrónico.';}
    //Borrado en la BD
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$regresar);
    $resultados = $select->execute()->fetchCol();

    $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
    $select->fields('r', array('id_rev_sitio_hall'));
    $select->condition('id_rev_sitio',$resultados, 'IN');
    $id_h = $select->execute()->fetchCol();

    $borrar = $connection->delete('revisiones_hallazgos')
      ->condition('id_rev_sitio', $resultados, 'IN')
      ->execute();
    $borrar = $connection->delete('revisiones_sitios')
      ->condition('id_rev_sitio', $resultados, 'IN')
      ->execute();
    $borrar = $connection->delete('revisiones_asignadas')
      ->condition('id_revision',$regresar)
      ->execute();
    $borrar = $connection->delete('revisiones')
      ->condition('id_revision',$regresar)
      ->execute();
    //consulta id_rev_sitio_hall
    $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $select->join('revisiones_sitios','s','h.id_rev_sitio = s.id_rev_sitio');
    $select->fields('h', array('id_rev_sitio_hall'));
    $select->condition('id_revision',$regresar);
    $idImg = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $connection = \Drupal::service('database');
    if(sizeof($idImg)){
      $elimina = $connection->delete('file_managed')
        ->condition('id_rev_sh', $idImg, 'IN')
        ->execute();
    }
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	$form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content', array()));
  }
}
