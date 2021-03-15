<?php

namespace Drupal\revisiones_asignadas\Controller;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Database;

class RevisionesAsignadasController {
  public function coordinador(){
    //Consulta de las revisiones que tiene el usuario
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
    $select->condition('seguimiento', false);
    $select->condition('id_usuario',\Drupal::currentUser()->id());
    $select->orderBy('fecha_inicio_revision','DESC');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    $datos = $select->execute();
    Database::setActiveConnection();

    //se recorren los resultados para después imprimirlos
    foreach ($datos as $result){
      if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
      //Botón para revisar
      $url = Url::fromRoute('informacion_revision.content', array('rev_id' => $result->id_revision));
      $project_link = Link::fromTextAndUrl('Revisar', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
      //Botón para borrar revisión
      $urlB = Url::fromRoute('borrar_revision.content', array('rev_id' => $result->id_revision));
      $project_linkB = Link::fromTextAndUrl('Borrar', $urlB);
      $project_linkB = $project_linkB->toRenderable();
      $project_linkB['#attributes'] = array('class' => array('button'));

      //Se busca el nombre de los pentesters que fueron asignados a la revision
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision', $result->id_revision);
      $select->condition('id_usuario', \Drupal::currentUser()->id(), '<>');
      $usuarios_rev = $select->execute()->fetchCol();

      //estatus_revision
      $select = Database::getConnection()->select('estatus_revisiones', 's');
      $select->fields('s', array('estatus'));
      $select->condition('id_estatus', $result->id_estatus);
      $estatus_revision = $select->execute()->fetchCol();

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
          $txt,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          render($project_link),
          render($project_linkB),
        ];
      }elseif($result->id_estatus < 3){
        $rows[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $nombres,
          $estatus_revision[0],
          $result->fecha_inicio_revision,
          $txt,
          render($project_linkB),
        ];
      }
    }
    //Se asignan titulos a cada columna
    $header1 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'pentesters' => t('Pentesters'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
      'start' => t('Fecha de asignacion'),
      'last' => t('Fecha de finalización'),
      'edit' => t('Editar'),
      'delete' => t('Borrar'),
    ];
    $header2 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'pentesters' => t('Pentesters'),
      'status' => t('Estado'),
      'start' => t('Fecha de asignacion'),
      'activos' => t('Activos asignados'),
      'delete' => t('Borrar'),
    ];
    //se construye la tabla para mostrar
    $concluidas['table'] = [
      '#type' => 'table',
      '#header' => $header1,
      '#rows' => $filas,
      '#empty' => t('Sin revisiones por revisar.'),
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
      '#empty' => t('No tienes revisiones asignadas en proceso.'),
    ];
    $url = Url::fromRoute('asignacion_revisiones.content', array());
    $project_link = Link::fromTextAndUrl('Asignar una nueva revision', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    //Revisiones pendientes de aprobacion
    $form['pendientes'] = [
      '#type' => 'item',
      '#title' => t('Revisiones en proceso'),
      '#markup' => render($pendientes),
    ];
    $form['boton'] = array('#markup' => render($project_link),);



    //Revisones de seguimiento
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->join('revisiones_asignadas',"revi","r.id_revision = revi.id_revision");
    $select->fields('r', array('id_revision'));
    $select->fields('r', array('tipo_revision'));
    $select->fields('r', array('id_estatus'));
    $select->fields('r', array('fecha_inicio_revision'));
    $select->fields('r', array('fecha_fin_revision'));
    $select->condition('seguimiento', false);
    $select->condition('id_usuario',\Drupal::currentUser()->id());
    $select->orderBy('fecha_inicio_revision','DESC');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    $datos = $select->execute();
    Database::setActiveConnection();

    //se recorren los resultados para después imprimirlos
    foreach ($datos as $result){
      if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
      //Botón para revisar
      $revisarS = Url::fromRoute('informacion_seguimiento.content', array('rev_id' => $result->id_revision));
      $revisarSeguimiento = Link::fromTextAndUrl('Revisar', $revisarS);
      $revisarSeguimiento = $revisarSeguimiento->toRenderable();
      $revisarSeguimiento['#attributes'] = array('class' => array('button'));
      //Botón para borrar revisión
      $borrarS = Url::fromRoute('borrar_revision.content', array('rev_id' => $result->id_revision));
      $borrarSeguimiento = Link::fromTextAndUrl('Borrar', $borrarS);
      $borrarSeguimiento = $borrarSeguimiento->toRenderable();
      $borrarSeguimiento['#attributes'] = array('class' => array('button'));

      //Se busca el nombre de los pentesters que fueron asignados a la revision
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision', $result->id_revision);
      $select->condition('id_usuario', \Drupal::currentUser()->id(), '<>');
      $usuarios_rev = $select->execute()->fetchCol();

      //estatus_revision
      $select = Database::getConnection()->select('estatus_revisiones', 's');
      $select->fields('s', array('estatus'));
      $select->condition('id_estatus', $result->id_estatus);
      $estatus_revision = $select->execute()->fetchCol();

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

      $select = Database::getConnection()->select('users_field_data', 'u');
      $select->fields('u', array('name'));
      $select->condition('uid', $usuarios_rev, 'IN');
      $pentesters = $select->execute()->fetchCol();
      $nombres = '';
      foreach ($pentesters as $pentester) {$nombres .= $pentester.', ';}
      $nombres = substr($nombres, 0, -2);
      if($result->id_estatus == 6){
        $filasS[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $nombres,
          $estatus_revision[0],
          $txt,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          render($revisarSeguimiento),
          render($borrarSeguimiento),
        ];
      }elseif($result->id_estatus == 5){
        $rowsS[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $nombres,
          $estatus_revision[0],
          $result->fecha_inicio_revision,
          $txt,
          render($borrarSeguimiento),
        ];
      }
    }
    //Se asignan titulos a cada columna
    $headerS1 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'pentesters' => t('Pentesters'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
      'start' => t('Fecha de asignacion'),
      'last' => t('Fecha de finalización'),
      'edit' => t('Editar'),
      'delete' => t('Borrar'),
    ];
    $headerS2 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'pentesters' => t('Pentesters'),
      'status' => t('Estado'),
      'start' => t('Fecha de asignacion'),
      'activos' => t('Activos asignados'),
      'delete' => t('Borrar'),
    ];
    //se construye la tabla para mostrar
    $segAsig['table'] = [
      '#type' => 'table',
      '#header' => $headerS1,
      '#rows' => $filasS,
      '#empty' => t('Sin revisiones de seguimiento por revisar.'),
    ];
    $segProc['table'] = [
      '#type' => 'table',
      '#header' => $headerS2,
      '#rows' => $rowsS,
      '#empty' => t('Sin revisiones de seguimiento en proceso.'),
    ];
    $form['segAsig'] = [
      '#type' => 'item',
      '#title' => t('Revisiones de seguimiento concluidas'),
      '#markup' => render($segAsig),
    ];
    $form['segProc'] = [
      '#type' => 'item',
      '#title' => t('Revisiones de seguimiento en proceso'),
      '#markup' => render($segProc),
    ];
    $form['pager'] = array('#type' => 'pager');
    return $form;
  }



  public function pentester(){
    //Consulta de las revisiones que tiene el usuario
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
    $select->condition('id_usuario',\Drupal::currentUser()->id());
    $select->condition('seguimiento', false);
    $select->orderBy('fecha_inicio_revision','DESC');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    $datos = $select->execute();

    Database::setActiveConnection();
    //se recorren los resultados para después imprimirlos
    foreach ($datos as $result){
      if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
      $url = Url::fromRoute('edit_revision.content', array('rev_id' => $result->id_revision));
      //$url = Url::fromRoute('asignacion_revisiones.content', array('rev_id' => $result->id_revision));
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

      //lista de sitios asignados
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
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
          $txt,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          $coordinador[0],
        ];
      }elseif($result->id_estatus < 3){
        $rows[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $estatus_revision[0],
          //$result->id_estatus,
          $txt,
          $result->fecha_inicio_revision,
          $coordinador[0],
          render($project_link),
        ];
      }
    }
    //Se asignan titulos a cada columna
    $header1 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
      'start' => t('Fecha de asignacion'),
      'coordinador' => t('Coordinador de revision'),
      'edit' => t('Editar'),
    ];
    $header2 = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
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



    //Revisiones de seguimiento
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
    $select->condition('id_usuario',\Drupal::currentUser()->id());
    $select->condition('seguimiento', false);
    $select->orderBy('fecha_inicio_revision','DESC');
    $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    $datosS = $select->execute();

    Database::setActiveConnection();
    //se recorren los resultados para después imprimirlos
    foreach ($datosS as $result){
      if($result->tipo_revision){$tipo = 'Circular';}else{$tipo = 'Oficio';}
      $urlS = Url::fromRoute('edit_seguimiento.content', array('rev_id' => $result->id_revision));
      $project_linkS = Link::fromTextAndUrl('Editar', $urlS);
      $project_linkS = $project_linkS->toRenderable();
      $project_linkS['#attributes'] = array('class' => array('button'));

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

      //lista de sitios asignados
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
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

      //estatus_revision
      $select = Database::getConnection()->select('estatus_revisiones', 's');
      $select->fields('s', array('estatus'));
      $select->condition('id_estatus', $result->id_estatus);
      $estatus_revision = $select->execute()->fetchCol();
      Database::setActiveConnection();
      if($result->id_estatus == 6){
        $filasS[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $estatus_revision[0],
          $txt,
          $result->fecha_inicio_revision,
          $result->fecha_fin_revision,
          $coordinador[0],
        ];
      }elseif($result->id_estatus == 5){
        $rowsS[$result->id_revision] = [
          $result->id_revision,
          $tipo,
          $estatus_revision[0],
          //$result->id_estatus,
          $txt,
          $result->fecha_inicio_revision,
          $coordinador[0],
          render($project_linkS),
        ];
      }
    }
    //Se asignan titulos a cada columna
    $header1S = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
      'start' => t('Fecha de asignacion'),
      'coordinador' => t('Coordinador de revision'),
      'edit' => t('Editar'),
    ];
    $header2S = [
      'id' => t('ID'),
      'type' => t('Tipo'),
      'status' => t('Estado'),
      'activos' => t('Activos asignados'),
      'start' => t('Fecha de asignacion'),
      'last' => t('Ultima fecha de modificacion'),
      'coordinador' => t('Coordinador de revision'),
    ];
    //se construye la tabla para mostrar
    $asignadasS['table'] = [
      '#type' => 'table',
      '#header' => $header1S,
      '#rows' => $rowsS,
      '#empty' => t('No tienes revisiones asignadas.'),
    ];
    $form['asignadasS'] = [
      '#type' => 'item',
      '#title' => t('Revisiones de seguimiento asignadas'),
      '#markup' => render($asignadasS),
    ];

    $pendientesS['table'] = [
      '#type' => 'table',
      '#header' => $header2S,
      '#rows' => $filasS,
      '#empty' => t('No tienes revisiones concluidas.'),
    ];
    //Revisiones pendientes de aprobacion
    $form['pendientesS'] = [
      '#type' => 'item',
      '#title' => t('Revisiones de seguimiento pendientes de aprobacion'),
      '#markup' => render($pendientesS),
    ];//*/
    $form['pager'] = array('#type' => 'pager');
    return $form;
  }

  public function revisiones(){
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles())){
      return $this->coordinador();
    }elseif (in_array('pentester', \Drupal::currentUser()->getRoles())){
      return $this->pentester();
    }else{
      return array('#markup' => "No tienes permiso para ver esta página",);
    }
  }
}