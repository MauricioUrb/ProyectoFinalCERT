<?php

namespace Drupal\revisiones_asignadas\Controller;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Database;

class RevisionesAsignadasController {
  public function revisiones(){
    $current_user_roles = \Drupal::currentUser()->getRoles();
    if (in_array('coordinador de revisiones', $current_user_roles)){
      //Consulta de las revisiones que tiene el usuario
      $user = \Drupal::currentUser()->id();

      //Campos de revisiones
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->join('revisiones_asignadas',"revi","r.id_revision = revi.id_revision");
      $select->fields('r', array('id_revision'));
      $select->fields('r', array('tipo_revision'));
      $select->fields('r', array('id_estatus'));
      $select->fields('r', array('fecha_inicio_revision'));
      $select->fields('r', array('fecha_fin_revision'));
      $select->condition('id_usuario',$user);
      $datos = $select->execute();
      Database::setActiveConnection();

      //se recorren los resultados para después imprimirlos
      foreach ($datos as $result){
        if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
        $url = Url::fromRoute('informacion_revision.content', array('rev_id' => $result->id_revision));
        $project_link = Link::fromTextAndUrl('Revisar', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        //Se busca el nombre de los pentesters que fueron asignados a la revision
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision', $result->id_revision);
        $select->condition('id_usuario', $user, '<>');
        $usuarios_rev = $select->execute()->fetchCol();

        //estatus_revision
        $select = Database::getConnection()->select('estatus_revisiones', 's');
        $select->fields('s', array('estatus'));
        $select->condition('id_estatus', $result->id_estatus);
        $estatus_revision = $select->execute()->fetchCol();
        Database::setActiveConnection();

        $select = Database::getConnection()->select('users_field_data', 'u');
        $select->fields('u', array('name'));
        $select->condition('uid', $usuarios_rev, 'IN');
        $pentesters = $select->execute()->fetchCol();
        $nombres = '';
        foreach ($pentesters as $pentester) {$nombres .= $pentester.', ';}
        $nombres = substr($nombres, 0, -2);
        if($result->id_estatus == 3){
          $filas[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $nombres,
            $estatus_revision[0],
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
            render($project_link),
          ];
        }else{
          $rows[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $nombres,
            $estatus_revision[0],
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
          ];
        }
      }
      //Se asignan titulos a cada columna
      $header1 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'pentesters' => t('Pentesters'),
        'status' => t('Estado'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Fecha de finalización'),
        'edit' => t('Editar'),
      ];
      $header2 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'pentesters' => t('Pentesters'),
        'status' => t('Estado'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Ultima fecha de modificacion'),
      ];
      //se construye la tabla para mostrar
      $concluidas['table'] = [
        '#type' => 'table',
        '#header' => $header1,
        '#rows' => $filas,
        '#empty' => t('Sin revisiones por revivar.'),
      ];
      $form['concluidas'] = [
        '#type' => 'item',
        '#title' => t('Revisiones concluidas'),
        '#markup' => render($concluidas),
      ];

      $pendientes['table'] = [
        '#type' => 'table',
        '#header' => $header2,
        '#rows' => $rows,
        '#empty' => t(''),
      ];
      //Revisiones pendientes de aprobacion
      $form['pendientes'] = [
        '#type' => 'item',
        '#title' => t('Revisiones en proceso'),
        '#markup' => render($pendientes),
      ];
    }elseif (in_array('pentester', $current_user_roles)){
      //Consulta de las revisiones que tiene el usuario
      $user = \Drupal::currentUser()->id();

      //Campos de revisiones
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->join('revisiones_asignadas',"revi","r.id_revision = revi.id_revision");
      $select->fields('r', array('id_revision'));
      $select->fields('r', array('tipo_revision'));
      $select->fields('r', array('id_estatus'));
      $select->fields('r', array('fecha_inicio_revision'));
      $select->fields('r', array('fecha_fin_revision'));
      $select->condition('id_usuario',$user);
      $datos = $select->execute();

      Database::setActiveConnection();
      //se recorren los resultados para después imprimirlos
      foreach ($datos as $result){
        if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
        $url = Url::fromRoute('edit_revision.content', array('rev_id' => $result->id_revision));
        $project_link = Link::fromTextAndUrl('Editar', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        //Se busca el nombre del coordinador que asignó la revision
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision', $result->id_revision);
        $usuarios_rev = $select->execute()->fetchCol();
        Database::setActiveConnection();

        $select = Database::getConnection()->select('user__roles', 'u');
        $select->join('users_field_data',"d","d.uid = u.entity_id");
        $select->fields('d', array('name'));
        $select->condition('d.uid', $usuarios_rev, 'IN');
        $select->condition('u.roles_target_id', 'coordinador de revisiones');
        $coordinador = $select->execute()->fetchCol();

        //Se busca si la revision tiene comentarios
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('comentarios', 'c');
        $select->fields('c', array('id_revision'));
        $select->condition('id_revision', $result->id_revision);
        $comentarios = $select->execute()->fetchCol();

        if(in_array($result->id_revision,$comentarios)){$comentario = 'Sí';}else{$comentario = '-';}
        //estatus_revision
        $select = Database::getConnection()->select('estatus_revisiones', 's');
        $select->fields('s', array('estatus'));
        $select->condition('id_estatus', $result->id_estatus);
        $estatus_revision = $select->execute()->fetchCol();
        Database::setActiveConnection();
        if($result->id_estatus == 3){
          $filas[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $estatus_revision[0],
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
            $coordinador[0],
          ];
        }else{
          $rows[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $estatus_revision[0],
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
            $coordinador[0],
            $comentario,
            render($project_link),
          ];
        }
      }
      //Se asignan titulos a cada columna
      $header1 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'status' => t('Estado'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Ultima fecha de modificacion'),
        'coordinador' => t('Coordinador de revision'),
        'coment' => t('Comentarios por atender'),
        'edit' => t('Editar'),
      ];
      $header2 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'status' => t('Estado'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Ultima fecha de modificacion'),
        'coordinador' => t('Coordinador de revision'),
      ];
      //se construye la tabla para mostrar
      $asignadas['table'] = [
        '#type' => 'table',
        '#header' => $header1,
        '#rows' => $rows,
        '#empty' => t('No tienes revisiones asignadas.'),
      ];
      $form['asignadas'] = [
        '#type' => 'item',
        '#title' => t('Revisiones asignadas'),
        '#markup' => render($asignadas),
      ];

      $pendientes['table'] = [
        '#type' => 'table',
        '#header' => $header2,
        '#rows' => $filas,
        '#empty' => t('No tienes revisiones concluidas.'),
      ];
      //Revisiones pendientes de aprobacion
      $form['pendientes'] = [
        '#type' => 'item',
        '#title' => t('Revisiones pendientes de aprobacion'),
        '#markup' => render($pendientes),
      ];
    }
    return $form;
  }
}
