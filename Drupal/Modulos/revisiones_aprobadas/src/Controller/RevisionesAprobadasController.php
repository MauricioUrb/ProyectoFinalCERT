<?php

namespace Drupal\revisiones_aprobadas\Controller;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Database;

class RevisionesAprobadasController {
  public function revisiones(){
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('pentester', \Drupal::currentUser()->getRoles())){
      //Campos de revisiones
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->fields('r', array('id_revision'));
      $select->fields('r', array('tipo_revision'));
      $select->fields('r', array('fecha_inicio_revision'));
      $select->fields('r', array('fecha_fin_revision'));
      $select->condition('id_estatus',4);
      $datos = $select->execute();
      Database::setActiveConnection();

      //se recorren los resultados para después imprimirlos
      foreach ($datos as $result){
        if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
        $url = Url::fromRoute('informacion_revision.content', array('rev_id' => $result->id_revision));
        //$url = Url::fromRoute('????.content', array('rev_id' => $result->id_revision));
        $project_link = Link::fromTextAndUrl('Descargar', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        //Se busca el nombre de los pentesters que fueron asignados a la revision
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision', $result->id_revision);
        $usuarios_rev = $select->execute()->fetchCol();

        //lista de sitios asignados
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->join('sitios',"s","r.id_sitio = s.id_sitio");
        $select->fields('s', array('url_sitio'));
        $select->condition('id_revision', $result->id_revision);
        $lista_sitios = $select->execute()->fetchCol();
        $txt = '';
        foreach ($lista_sitios as $sitio) {
          $txt .= $sitio . ' , ';
        }
        $txt = substr($txt, 0, -3);
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

        $rows[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $coordinador[0],
          $nombres,
          $txt,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          render($project_link),
        ];
      }
      //Se asignan titulos a cada columna
      $header1 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'coordinador' => t('Coordinador de revisiones'),
        'pentesters' => t('Pentesters'),
        'activos' => t('Activos asignados'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Fecha de finalización'),
        'edit' => t('Descargar reporte'),
      ];
      //se construye la tabla para mostrar
      $concluidas['table'] = [
        '#type' => 'table',
        '#header' => $header1,
        '#rows' => $rows,
        '#empty' => t('Sin revisiones por revivar.'),
      ];
      $form['concluidas'] = [
        '#type' => 'item',
        '#title' => t('Revisiones concluidas'),
        '#markup' => render($concluidas),
      ];
    }else{
      return array('#markup' => "No tienes permiso para ver esta página",);
    }
    return $form;
  }
}