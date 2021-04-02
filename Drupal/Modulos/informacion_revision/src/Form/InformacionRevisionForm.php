<?php
/*
 * @file
 * Contains \Drupal\informacion_revision\Form\InformacionRevisionForm
 */
namespace Drupal\informacion_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/*
 *
 */
class InformacionRevisionForm extends FormBase{
  public function getFormId(){
    return 'informacion_revision_form';
  }

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
    Database::setActiveConnection();
    $current_user_roles = \Drupal::currentUser()->getRoles();
    if (!in_array(\Drupal::currentUser()->id(), $results) || !in_array('coordinador de revisiones', $current_user_roles) || $estatus[0] != 3){
    	return array('#markup' => "No tienes permiso para ver esta página.".$estatus[0]."s",);
    }
    //Se obtienen los sitios correspondientes a esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('tipo_revision'));
    //$select->fields('r', array('fecha_inicio_revision'));
    //$select->fields('r', array('fecha_fin_revision'));
    $select->condition('id_revision',$rev_id);
    $tipo_revision = $select->execute()->fetchCol();
    //Fecha de inicio
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('actividad', 'r');
    $select->fields('r', array('fecha'));
    $select->condition('id_revision',$rev_id);
    $select->condition('id_estatus',1);
    $fecha_inicio_revision = $select->execute()->fetchCol();
    //Fecha de fin
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('actividad', 'r');
    $select->fields('r', array('fecha'));
    $select->condition('id_revision',$rev_id);
    $select->condition('id_estatus',2);
    $fecha_fin_revision = $select->execute()->fetchCol();

    //Se obtienen los pentesters
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision', $rev_id);
    $usuarios_rev = $select->execute()->fetchCol();

    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id','pentester');
    $pentesters = $select->execute()->fetchCol();
    $nombres = '';
    foreach ($pentesters as $pentester) {$nombres .= $pentester.', ';}
    $nombres = substr($nombres, 0, -2);
    //Se imprime en pantalla los datos de la revision
    $form['id'] = array(
      '#type' => 'item',
      '#title' => 'Id de revisión:',
      '#markup' => $rev_id,
    );
    if($tipo_revision[0]){$tipo = 'Circular';}else{$tipo = 'Oficio';}
    $form['tipo'] = array(
      '#type' => 'item',
      '#title' => 'Tipo:',
      '#markup' => $tipo,
    );
    $form['inicio'] = array(
      '#type' => 'item',
      '#title' => 'Fecha de asignación:',
      '#markup' => $fecha_inicio_revision[0],
    );
    $form['fecha'] = array(
      '#type' => 'item',
      '#title' => 'Fecha de finalización:',
      '#markup' => $fecha_fin_revision[0],
    );
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
      $select->fields('r', array('id_rev_sitio_hall'));
      $select->fields('r', array('descripcion_hall_rev'));
      $select->fields('r', array('recursos_afectador'));
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
        );
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['recursos'] = array(
          '#type' => 'item',
          '#title' => 'Recursos afectados:',
          '#markup' => $hallazgo->recursos_afectador,
        );
        
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        $select = $connection->select('file_managed', 'fm')
          ->fields('fm', array('fid', 'filename', 'descripcion'));
        $select->condition('id_rev_sh', $hallazgo->id_rev_sitio_hall);
        $results = $select->execute();
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        foreach ($results as $result){
          $rows[$id->id_rev_sitio][$hallazgo->id_hallazgo][$result->fid] = [
            $result->filename,
            Markup::create("<a href='/sites/default/files/content/evidencia/$result->filename'>Imagen</a>"),
            $result->descripcion,
          ];
        }
        $header = [
          'name' => t('Nombre'),
          'image' => t('Imagen'),
          'descripcion' => t('Descripcion'),
        ];
        //se construye la tabla para mostrar
        $build[$id->id_rev_sitio][$hallazgo->id_hallazgo]['table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows[$id->id_rev_sitio][$hallazgo->id_hallazgo],
          '#empty' => t('Nada para mostrar.'),
        ];
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['build'] = [
          '#type' => '#markup',
          '#markup' => render($build[$id->id_rev_sitio][$hallazgo->id_hallazgo]),
        ];
        $form[$id->id_rev_sitio][$hallazgo->id_hallazgo]['impacto'] = array(
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
    //Botones
    $urlComentar = Url::fromRoute('comentar_revision.content', array('rev_id' => $rev_id));
    $comentar = Link::fromTextAndUrl('Realizar un comentario', $urlComentar);
    $comentar = $comentar->toRenderable();
    $comentar['#attributes'] = array('class' => array('button'));
    $urlAprobar = Url::fromRoute('aprobar_revision.content', array('rev_id' => $rev_id));
    $aprobacion = Link::fromTextAndUrl('Aprobar revision', $urlAprobar);
    $aprobacion = $aprobacion->toRenderable();
    $aprobacion['#attributes'] = array('class' => array('button'));
    
    $form['botones'] = array(
      '#type' => 'item',
      '#title' => '',
      '#markup' => render($comentar).render($aprobacion),
    );
  	return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}