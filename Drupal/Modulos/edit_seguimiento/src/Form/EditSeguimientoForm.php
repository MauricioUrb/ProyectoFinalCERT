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
    $results = $select->execute()->fetchCol();
    //estatus_seguimiento
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] > 2){
    	return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    global $id_revS;
    global $id_revOr;
    global $arreglo_global;
    $arreglo_global = array();
    $id_revS = $rev_id;
    //Datos de la revisión original
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('id_seguimiento'));
    $select->condition('id_revision',$rev_id);
    $rev_idO = $select->execute()->fetchCol();
    $id_revOr = $rev_idO[0];
    $form['id'] = array(
      '#type' => 'item',
      '#title' => t('ID de revisón mandada a seguimiento:'),
      '#markup' => $rev_idO[0],
    );
    //Se obtienen los datos
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios', 's', 'r.id_sitio = s.id_sitio');
    $select->fields('s', array('url_sitio'));
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_idO[0]);
    $ids = $select->execute();
    $tmp = 1;
    foreach ($ids as $id) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_rev_sitio_hall'));
      $select->fields('r', array('descripcion_hall_rev'));
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

    //Sección para agregar nuevos hallazgos
    $form['nuevosH'] = array(
      '#type' => 'item',
      '#title' => t('Agregar nuevos hallazgos (Opcional)'),
    );
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
        $urlImg = Url::fromRoute('mostrar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $id_rev_sitio_hall[0], 'seg' => 1));
        $imagenes = Link::fromTextAndUrl('Ver imágenes', $urlImg);
        $imagenes = $imagenes->toRenderable();
        $imagenes['#attributes'] = array('class' => array('button'));
        //Boton de editar
        $urlEditar = Url::fromRoute('asignar_hallazgos.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'hall_id' => $hallazgo->id_hallazgo, 'seg' => 1));
        $editar = Link::fromTextAndUrl('Editar', $urlEditar);
        $editar = $editar->toRenderable();
        $editar['#attributes'] = array('class' => array('button'));
        //Boton de borrar
        $urlBorrar = Url::fromRoute('delete_hallazgo_revision.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'hall_id' => $hallazgo->id_hallazgo, 'rsh' => $id_rev_sitio_hall[0], 'seg' => 1));
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
      $hallazgosT[$contador]['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows[$contador],
        '#empty' => t('No tienes hallazgos asignados a este activo.'),
      ];
      $url = Url::fromRoute('select_hallazgo.content', array('rev_id' => $rev_id,'id_rev_sitio' => $result->id_rev_sitio, 'seg' => 1));
      $project_link = Link::fromTextAndUrl('Agregar hallazgo', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
      $form[$contador] = array(
        '#type' => 'item',
        '#title' => $result->url_sitio,
        '#markup' => render($hallazgosT[$contador]),
        '#description' => render($project_link),
      );//*/
      $contador++;
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
    $concluido = $form_state->getValue(['estatus']);
    global $id_revOr;
    global $id_revS;
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
    Database::setActiveConnection();
    if($concluido){
      $mensaje = 'Seguimiento mandado para aprobación.';
      //Se busca el nombre del coordinador que asignó la revision
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision', $id_revS);
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
      $params['context']['message'] = 'Los pentesters han conlcuido la revision de seguimiento #'.$id_revS.' que les fue asignada.';
      $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $mail[0], $langcode, $params);
      if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $result = $connection->insert('actividad')
        ->fields(array(
          'id_revision' => $id_revS,
          'id_estatus' => 3,
          'fecha' => $hoy,
        ))
        ->execute();
      Database::setActiveConnection();
  	}else{
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //Se revisa si ya se tiene ese estado, de otro modo, se actualiza
      $select = Database::getConnection()->select('actividad', 'a');
      $select->fields('a', array('id_actividad'));
      $select->condition('id_revision', $id_revS);
      $select->condition('id_estatus', 2);
      $existe = $select->execute()->fetchCol();
      if(sizeof($existe)){
        $update = $connection->update('actividad')
          ->fields(array(
            'fecha' => $hoy,
          ))
          ->condition('id_actividad',$existe[0])
          ->execute();
      }else{
        $update = $connection->insert('actividad')
          ->fields(array(
            'id_revision' => $id_revS,
            'id_estatus' => 2,
            'fecha' => $hoy,
          ))
          ->execute();
      }
      Database::setActiveConnection();
    }

    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_asignadas.content'));
  }
}
