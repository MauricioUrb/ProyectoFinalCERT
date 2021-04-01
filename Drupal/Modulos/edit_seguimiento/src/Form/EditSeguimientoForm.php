<?php
/*
 * @file
 * Contains \Drupal\edit_seguimiento\Form\AsignacionrevisionesForm
 */
namespace Drupal\edit_seguimiento\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/*
 *
 */
class EditSeguimientoForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'edit_seguimiento_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta seguimiento
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento',true);
    $results = $select->execute()->fetchCol();
    //estatus_seguimiento
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('id_estatus'));
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] != 5){
    	return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    global $id_rev;
    global $arreglo_global;
    $arreglo_global = array();
    $id_rev = $rev_id;
    //Datos de la revisión
    $form['id'] = array(
      '#type' => 'item',
      '#title' => t('ID de revisón a mandar a seguimiento:'),
      '#markup' => $rev_id,
    );
    //Se obtienen los datos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios', 's', 'r.id_sitio = s.id_sitio');
    $select->fields('s', array('url_sitio'));
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_id);
    $ids = $select->execute();
    $tmp = 1;
    foreach ($ids as $id) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_rev_sitio_hall'));
      $select->fields('r', array('descripcion_hall_rev'));
      //$select->fields('r', array('recursos_afectador'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->fields('r', array('cvss_hallazgos'));
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('estatus'));
      $select->condition('id_rev_sitio',$id->id_rev_sitio);
      $select->orderBy('impacto_hall_rev','DESC');
      $datHall = $select->execute();
      $form[$id->id_rev_sitio] = array(
        '#type' => 'item',
        '#title' => 'Sitio:',
        '#markup' => $id->url_sitio,
      );
      $hallazgos = array();
      foreach ($datHall as $hallazgo) {
        //Nombre del hallazgo
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->condition('id_hallazgo',$hallazgo->id_hallazgo);
        $nombreHallazgo = $select->execute()->fetchCol();
        //Se imprime en pantalla los datos correspondiente al sitio-hallazgo
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall] = array(
          '#type' => 'fieldset',
          '#collapsible' => TRUE, 
          '#collapsed' => FALSE,
        );
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall]['hallazgo'] = array(
          '#type' => 'item',
          '#title' => 'Hallazgo:',
          '#markup' => $nombreHallazgo[0],
        );
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall]['descripcion'] = array(
          '#type' => 'item',
          '#title' => 'Descripción:',
          '#markup' => $hallazgo->descripcion_hall_rev,
        );
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall]['impacto'] = array(
          '#type' => 'item',
          '#title' => 'Impacto',
          '#markup' => $hallazgo->impacto_hall_rev,
        );
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall]['cvss'] = array(
          '#type' => 'item',
          '#title' => 'CVSS:',
          '#markup' => $hallazgo->cvss_hallazgos,
        );
        array_push($hallazgos, $hallazgo->id_rev_sitio_hall);
        if($hallazgo->estatus){$estatus = 0;}else{$estatus = 1;}
        $form[$id->id_rev_sitio][$hallazgo->id_rev_sitio_hall]['select'.$tmp] = array(
          '#type' => 'radios',
          '#title' => t('Estatus:'),
          '#default_value' => $estatus,
          '#options' => ['Persistente', 'Mitigado'],
          '#required' => TRUE,
        );
        $tmp++;
      }
      $arreglo_global[$id->id_rev_sitio] = $hallazgos;
    }
    Database::setActiveConnection();
    //Estatus
    $form['estatus'] = array(
      '#type' => 'radios',
      '#title' => t('Actualizar estatus:'),
      '#default_value' => 0,
      '#options' => array(0 => 'En proceso', 1 => 'Concluir'),
    );
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
    );
    return $form;
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    $mensaje = 'Seguimiento guardado.';
    //$concluido = $form_state->getValue(['estatus']);
    global $id_rev;
    global $arreglo_global;
    $tmp = 1;
    //$opciones = array(0 => 'Persistente', 1 => 'Mitigado');
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    foreach ($arreglo_global as $id_rs => $arrH) {
      foreach ($arrH as $hallazgo) {
        foreach ($form_state->getValue([$id_rs][$hallazgo]) as $key => $value) {
          if($key == 'select'.$tmp && $value){
            $result = $connection->update('revisiones_hallazgos')
                ->fields(array(
                  'estatus' => 0,
                ))
              ->condition('id_rev_sitio_hall', $hallazgo)
              ->execute();
            break;
          }elseif($key == 'select'.$tmp && !$value){
            $result = $connection->update('revisiones_hallazgos')
                ->fields(array(
                  'estatus' => 1,
                ))
              ->condition('id_rev_sitio_hall', $hallazgo)
              ->execute();
            break;
          }
        }
        $tmp++;
      }
    }
    $fecha = getdate();
    $hoy = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];
    if($form_state->getValue(['estatus'])){$id_estatus = 6;}else{$id_estatus = 5;}
    $result = $connection->update('revisiones')
      ->fields(array(
        'id_estatus' => $id_estatus,
        'fecha_fin_seguimiento' => $hoy,
      ))
    ->condition('id_revision', $id_rev)
    ->execute();
    Database::setActiveConnection();
    if($form_state->getValue(['estatus'])){
      $mensaje = 'Seguimiento mandado para aprobación.';
      //Se busca el nombre del coordinador que asignó la revision
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision', $id_rev);
      $select->condition('seguimiento', true);
      $usuarios_rev = $select->execute()->fetchCol();
      Database::setActiveConnection();

      $select = Database::getConnection()->select('user__roles', 'u');
      $select->join('users_field_data',"d","d.uid = u.entity_id");
      $select->fields('d', array('mail'));
      $select->condition('d.uid', $usuarios_rev, 'IN');
      $select->condition('u.roles_target_id', 'coordinador de revisiones');
      $mail = $select->execute()->fetchCol();
      //Se manda el correo al coordinador
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $params['context']['subject'] = "Revision concluida";
      $params['context']['message'] = 'Los pentesters han conlcuido la revision de seguimiento #'.$id_rev.' que les fue asignada.';
      $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $mail[0], $langcode, $params);
      if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
  	}

    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}
