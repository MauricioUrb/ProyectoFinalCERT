<?php
/*
 * @file
 * Contains \Drupal\edit_revision\Form\AsignacionRevisionesForm
 */
namespace Drupal\edit_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/*
 *
 */
class EditRevisionForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'edit_revision_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento', false);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('id_estatus'));
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] > 2){
    	return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    global $id_rev;
    $id_rev = $rev_id;
    //Se obtienen los sitios correspondientes a esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios', 's', 'r.id_sitio = s.id_sitio');
    $select->fields('s', array('url_sitio'));
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute();
    $contador = 0;
    foreach ($results as $result) {
      //Se consultan los hallazgos de este sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->fields('h', array('id_hallazgo'));
      $select->fields('h', array('impacto_hall_rev'));
      $select->fields('h', array('cvss_hallazgos'));
      $select->condition('h.id_rev_sitio',$result->id_rev_sitio);
      $datHall = $select->execute();
      foreach ($datHall as $hallazgo) {
        //Obtener el id_rev_sitio_hall
        $consulta = Database::getConnection()->select('revisiones_hallazgos', 'h');
        $consulta->fields('h', array('id_rev_sitio_hall'));
        $consulta->condition('id_rev_sitio',$result->id_rev_sitio);
        $consulta->condition('id_hallazgo',$hallazgo->id_hallazgo);
        $id_rev_sitio_hall = $consulta->execute()->fetchCol();
        //Boton de imagenes
        $urlImg = Url::fromRoute('mostrar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $id_rev_sitio_hall[0]));
        $imagenes = Link::fromTextAndUrl('Ver imágenes', $urlImg);
        $imagenes = $imagenes->toRenderable();
        $imagenes['#attributes'] = array('class' => array('button'));
        //Boton de editar
        $urlEditar = Url::fromRoute('asignar_hallazgos.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'hall_id' => $hallazgo->id_hallazgo));
        $editar = Link::fromTextAndUrl('Editar', $urlEditar);
        $editar = $editar->toRenderable();
        $editar['#attributes'] = array('class' => array('button'));
        //Boton de borrar
        $urlBorrar = Url::fromRoute('delete_hallazgo_revision.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'hall_id' => $hallazgo->id_hallazgo, 'rsh' => $id_rev_sitio_hall[0]));
        $borrar = Link::fromTextAndUrl('Borrar', $urlBorrar);
        $borrar = $borrar->toRenderable();
        $borrar['#attributes'] = array('class' => array('button'));
        //Primero se obtiene el nombre del hallazgo
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->condition('id_hallazgo',$hallazgo->id_hallazgo);
        $nombreHallazgo = $select->execute()->fetchCol();
        $rows[$contador][$hallazgo->id_hallazgo] = [
          $nombreHallazgo[0],
          $hallazgo->impacto_hall_rev,
          $hallazgo->cvss_hallazgos,
          render($imagenes),
          render($editar),
          render($borrar),
        ];
      }
      //Se asignan titulos a cada columna
      $header = [
        'hallazgo' => t('Hallazgo'),
        'impact' => t('Impacto'),
        'cvss' => t('CVSS'),
        'evidencia' => t('Evidencia'),
        'edit' => t('Editar'),
        'delete' => t('Borrar'),
      ];
      //se construye la tabla para mostrar
      $hallazgos[$contador]['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows[$contador],
        '#empty' => t('No tienes hallazgos asignados a este activo.'),
      ];
      $url = Url::fromRoute('asignar_hallazgos.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'hall_id' => 0));
      $project_link = Link::fromTextAndUrl('Agregar hallazgo', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
      $form[$contador] = array(
        '#type' => 'item',
        '#title' => $result->url_sitio,
        '#markup' => render($hallazgos[$contador]),
        '#description' => render($project_link),
      );
      $contador++;
    }
    Database::setActiveConnection();
    //En proceso o concluido
    $form['estatus'] = array(
      '#type' => 'radios',
      '#title' => t('Actualizar estatus:'),
      '#default_value' => isset($node->active) ? $node->active : 0,
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
    $mensaje = 'Revision guardada.';
    $concluido = $form_state->getValue(['estatus']);
    global $id_rev;
    //Si se concluye la revision, se manda correo al coordinador
  	if($concluido){
      $mensaje = 'Revision enviada al coordinador de revisiones.';
      //Se busca el nombre del coordinador que asignó la revision
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision', $id_rev);
      $select->condition('seguimiento', false);
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
      $params['context']['message'] = 'Los pentesters han conlcuido la revision #'.$id_rev.' que les fue asignada.';
      $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $mail[0], $langcode, $params);
      if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
      //UPDATE EN LA BD
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $tmp = getdate();
      $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
      $update = $connection->update('revisiones')
        ->fields(array(
          'fecha_fin_revision' => $fecha,
          'id_estatus' => 3,
        ))
        ->condition('id_revision',$id_rev)
        ->execute();
      Database::setActiveConnection();
    }else{
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $tmp = getdate();
      $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
      $update = $connection->update('revisiones')
        ->fields(array(
          'id_estatus' => 2,
        ))
        ->condition('id_revision',$id_rev)
        ->execute();
      Database::setActiveConnection();
    }
    //db_query("DELETE FROM {cache};");
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}
