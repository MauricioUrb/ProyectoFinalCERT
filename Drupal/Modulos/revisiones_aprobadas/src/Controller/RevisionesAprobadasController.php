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
      $select->fields('r', array('id_estatus'));
      $select->fields('r', array('tipo_revision'));
      $select->fields('r', array('fecha_inicio_revision'));
      $select->fields('r', array('fecha_fin_revision'));
      $select->condition('id_estatus',4,'>=');
      $select->orderBy('fecha_fin_revision','DESC');
      $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      $datos = $select->execute();
      Database::setActiveConnection();

      //se recorren los resultados para después imprimirlos
      foreach ($datos as $result){
        if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
        
        //Se busca el nombre de los pentesters que fueron asignados a la revision
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision', $result->id_revision);
        $select->condition('seguimiento', false);
        $usuarios_rev = $select->execute()->fetchCol();

        //lista de sitios asignados
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->join('sitios',"s","r.id_sitio = s.id_sitio");
        $select->fields('s', array('url_sitio'));
        $select->condition('id_revision', $result->id_revision);
        $lista_sitios = $select->execute()->fetchCol();
        $nombreSitios = '';
        foreach ($lista_sitios as $sitio) {
          $nombreSitios .= $sitio . ' , ';
        }
        $nombreSitios = substr($nombreSitios, 0, -3);
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

        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('seguimiento '));
        $select->condition('id_revision', $result->id_revision);
        $seguimiento = $select->execute()->fetchCol();
        Database::setActiveConnection();

        //Botón para descargar reporte
        list($year,$month,$day) = explode('-', $result->fecha_fin_revision);
        if(strlen((string)$month) == 1){
          $mes = '0'.$month;
        }else{$mes = $month;}
        if($result->tipo_revision){
          $nombreArchivo = $year.$mes.'_'.$lista_sitios[0].'_REV'.$result->id_revision . '_' . $tipo.'.docx';
        }else{
          if(sizeof($lista_sitios) == 1){
            $nombreArchivo = $year.$mes.'_'.$lista_sitios[0].'_REV'.$result->id_revision . '_' . $tipo.'.docx';
          }else{
            $nombreArchivo = $year.$mes.'_variosSitios_REV'.$result->id_revision . '_' . $tipo.'.docx';
          }
        }
        //$nombreArchivo = 'helloWorld.docx';
        $url = Url::fromUri('http://' . $_SERVER['SERVER_NAME'] . '/reportes/' . $nombreArchivo);
        $descargar = Link::fromTextAndUrl('Descargar', $url);
        $descargar = $descargar->toRenderable();
        $descargar['#attributes'] = array('class' => array('button'));

        $rows[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $coordinador[0],
          $nombres,
          $nombreSitios,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          render($descargar),
        ];
        
        $url1 = Url::fromRoute('asignacion_revisiones.content', array('rev_id' => $rev_id));
        $revisiones = Link::fromTextAndUrl('Seguimiento', $url1);
        $revisiones = $revisiones->toRenderable();
        $revisiones['#attributes'] = array('class' => array('button'));
        if($result->id_estatus == 4){
          $filas[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $coordinador[0],
            $nombres,
            $nombreSitios,
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
            render($descargar),
            render($revisiones),
          ];
        }else{
          $filas[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $coordinador[0],
            $nombres,
            $nombreSitios,
            $result->fecha_inicio_revision,
            $result->fecha_fin_revision,
            render($descargar),
            '-',
          ];
        }
      }

      //Tabla de revisiones de seguimiento
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->fields('r', array('id_revision'));
      $select->fields('r', array('id_estatus'));
      $select->fields('r', array('tipo_revision'));
      //$select->fields('r', array('fecha_inicio_seguimiento'));
      //$select->fields('r', array('fecha_fin_seguimiento'));
      $select->condition('id_estatus',7);
      $select->orderBy('fecha_fin_revision','DESC');
      $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      $datos = $select->execute();
      Database::setActiveConnection();
      foreach ($variable as $key => $value) {
        if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
        
        //Se busca el nombre de los pentesters que fueron asignados a la revision
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision', $result->id_revision);
        $select->condition('seguimiento', true);
        $usuarios_rev = $select->execute()->fetchCol();

        //lista de sitios asignados
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->join('sitios',"s","r.id_sitio = s.id_sitio");
        $select->fields('s', array('url_sitio'));
        $select->condition('id_revision', $result->id_revision);
        $lista_sitios = $select->execute()->fetchCol();
        $nombreSitios = '';
        foreach ($lista_sitios as $sitio) {
          $nombreSitios .= $sitio . ' , ';
        }
        $nombreSitios = substr($nombreSitios, 0, -3);
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

        $nombreArchivoS = 'helloWorld.docx';
        $url2 = Url::fromUri('http://' . $_SERVER['SERVER_NAME'] . '/reportes/' . $nombreArchivoS);
        $descargar1 = Link::fromTextAndUrl('Descargar', $url2);
        $descargar1 = $descargar1->toRenderable();
        $descargar1['#attributes'] = array('class' => array('button'));
        //Tabla de revisiones de seguimiento
        $ultima[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $coordinador[0],
          $nombres,
          $nombreSitios,
          //$result->fecha_inicio_seguimiento,
          //$result->fecha_fin_seguimiento,
          render($descargar),
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
      $header2 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'coordinador' => t('Coordinador de revisiones'),
        'pentesters' => t('Pentesters'),
        'activos' => t('Activos asignados'),
        'start' => t('Fecha de asignacion'),
        'last' => t('Fecha de finalización'),
        'edit' => t('Descargar reporte'),
        'seguimiento' => t('Mandar a seguimiento'),
      ];
      $header3 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'coordinador' => t('Coordinador de revisiones'),
        'pentesters' => t('Pentesters'),
        'activos' => t('Activos asignados'),
        //'start' => t('Fecha de asignacion'),
        //'last' => t('Fecha de finalización'),
        'edit' => t('Descargar reporte'),
      ];
      //se construye la tabla para mostrar
      if(!in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles())){
        $concluidas['table'] = [
          '#type' => 'table',
          '#header' => $header1,
          '#rows' => $rows,
          '#empty' => t('Sin revisiones aprobadas.'),
        ];
      }else{
        $concluidas['table'] = [
          '#type' => 'table',
          '#header' => $header2,
          '#rows' => $filas,
          '#empty' => t('Sin revisiones aprobadas.'),
        ];
      }
      $last_T['table'] = [
        '#type' => 'table',
        '#header' => $header3,
        '#rows' => $ultima,
        '#empty' => t('Sin revisiones por mostrar.'),
      ];
      $form['concluidas'] = [
        '#type' => 'item',
        '#title' => t('Revisiones concluidas'),
        '#markup' => render($concluidas),
      ];
      $form['last_T'] = [
        '#type' => 'item',
        '#title' => t('Revisiones de seguimiento concluidas'),
        '#markup' => render($last_T),
      ];
      $form['pager'] = array('#type' => 'pager');
      /*
      $url = Url::fromUri('http://' . $_SERVER['SERVER_NAME'] . '/reportes/202103_variosSitios_REV3_Oficio.docx');
      $project_link = Link::fromTextAndUrl('Descargar', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
      $form['test'] = array('#markup' => render($project_link));
      //*/
    }else{
      return array('#markup' => "No tienes permiso para ver esta página",);
    }
    return $form;
  }
}