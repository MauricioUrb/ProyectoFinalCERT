<?php

namespace Drupal\revisiones_aprobadas\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Database;

class RevisionesAprobadasForm extends FormBase{
  public function getFormId(){
    return 'revisiones_aprobadas_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('pentester', \Drupal::currentUser()->getRoles())){
            $form['opcion'] = array('#markup' => "Filtrar resultados",);
      // Filter field.
      $form['id_rev'] = array(
        '#type' => 'textfield',
        '#title' => t('ID de revisión:'),
        '#size' => 15,
        '#default_value' => $form_state->getValue(['id_rev']) ? $form_state->getValue(['id_rev']) : '',
      );
      $form['tipo_r'] = array(
        '#title' => t('Tipo de revisión:'),
        '#type' => 'radios',
        '#options' => array(0 => 'Sin filtro', 1 => 'Oficio', 2 => 'Circular'),
        '#default_value' => 0,
      );
      $form['coord_n'] = array(
        '#type' => 'textfield',
        '#title' => t('Coordinador de revisión:'),
        '#size' => 20,
        '#default_value' => $form_state->getValue(['coord_n']) ? $form_state->getValue(['coord_n']) : '',
      );
      $form['pent_n'] = array(
        '#type' => 'textfield',
        '#title' => t('Pentester asignado:'),
        '#size' => 20,
        '#default_value' => $form_state->getValue(['pent_n']) ? $form_state->getValue(['pent_n']) : '',
      );
      $form['act_n'] = array(
        '#type' => 'textfield',
        '#title' => t('Activo asignado:'),
        '#size' => 50,
        '#default_value' => $form_state->getValue(['act_n']) ? $form_state->getValue(['act_n']) : '',
      );
      $form['fechaI'] = array(
        '#type' => 'textfield',
        '#title' => t('Fecha de asignación:'),
        '#size' => 15,
        '#default_value' => $form_state->getValue(['fechaI']) ? $form_state->getValue(['fechaI']) : '',
        '#description' => 'Formato: YYYY o YYYY-MM o YYYY-MM-DD',
        '#maxlength' => 10,
      );
      $form['fechaF'] = array(
        '#type' => 'textfield',
        '#title' => t('Fecha de finalización:'),
        '#size' => 15,
        '#default_value' => $form_state->getValue(['fechaF']) ? $form_state->getValue(['fechaF']) : '',
        '#description' => 'Formato: YYYY o YYYY-MM o YYYY-MM-DD',
        '#maxlength' => 10,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t("Buscar"),  
      );
      $urlR = Url::fromRoute('revisiones_aprobadas.content', array());
      $project_linkR = Link::fromTextAndUrl('Limpiar filtros', $urlR);
      $project_linkR = $project_linkR->toRenderable();
      $project_linkR['#attributes'] = array('class' => array('button'));
      $form['regresar'] = array('#markup' => render($project_linkR),);

      //Campos de revisiones
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->join('actividad','a','a.id_revision = r.id_revision');
      $select->fields('r', array('id_revision'));
      $select->fields('r', array('tipo_revision'));
      $select->condition('seguimiento', 0);
      $select->condition('id_estatus',4);
      $select->orderBy('fecha','DESC');
      if(!$form_state->getValue(['filtro'])){
        $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      }
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
        $select->condition('roles_target_id', ['coordinador de revisiones','auxiliar'],'IN');
        //$select->condition('roles_target_id','coordinador de revisiones');
        $coordinador = $select->execute()->fetchCol();

        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        
        //Fecha inicio revision
        $select = Database::getConnection()->select('actividad', 'r');
        $select->fields('r', array('fecha'));
        $select->condition('id_revision', $result->id_revision);
        $select->condition('id_estatus', 1);
        $fecha_inicio_revision = $select->execute()->fetchCol();
        //Fecha fin revision
        $select = Database::getConnection()->select('actividad', 'r');
        $select->fields('r', array('fecha'));
        $select->condition('id_revision', $result->id_revision);
        $select->condition('id_estatus', 4);
        $fecha_fin_revision = $select->execute()->fetchCol();
        Database::setActiveConnection();
        //Botón para descargar reporte
        list($year,$month,$day) = explode('-', $fecha_fin_revision[0]);
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
          $fecha_inicio_revision[0],
          $fecha_fin_revision[0],
          render($descargar),
        ];
        
        $url1 = Url::fromRoute('asignacion_seguimiento.content', array('rev_id' => $result->id_revision));
        $revisiones = Link::fromTextAndUrl('Seguimiento', $url1);
        $revisiones = $revisiones->toRenderable();
        $revisiones['#attributes'] = array('class' => array('button'));
        if(!in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles())){
          $rows[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $coordinador[0],
            $nombres,
            $nombreSitios,
            $fecha_inicio_revision[0],
            $fecha_fin_revision[0],
            render($descargar),
          ];
        }else{
          $rows[$result->id_revision] = [
            $result->id_revision,
            $tipo,
            $coordinador[0],
            $nombres,
            $nombreSitios,
            $fecha_inicio_revision[0],
            $fecha_fin_revision[0],
            render($descargar),
            render($revisiones),
          ];
        }
      }

      //Filtros
      $filtro = false;
      if($form_state->getValue(['id_rev'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if($form_state->getValue(['id_rev']) == $value[0]){
            $ultima[$id] = $value;
          }
        }
        $rows = $ultima;
        $filas = $ultima;
      }
      if($form_state->getValue(['tipo_r']) != 0){
        $filtro = true;
        $ultima = array();
        if($form_state->getValue(['tipo_r']) == 1){$type = 'Oficio';}else{$type = 'Circular';}
        foreach ($rows as $id => $value) {
          if(preg_match("/".$type."/", $value[1])){
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      if($form_state->getValue(['coord_n'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if(preg_match("/".$form_state->getValue(['coord_n'])."/", $value[2])){
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      if($form_state->getValue(['pent_n'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if(preg_match("/".$form_state->getValue(['pent_n'])."/", $value[3])){
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      if($form_state->getValue(['act_n'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if(preg_match("/".$form_state->getValue(['act_n'])."/", $value[4])){
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      if($form_state->getValue(['fechaI'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if(str_starts_with($value[5], $form_state->getValue(['fechaI']))){
            $form['test'] = array('#markup' => $form_state->getValue(['fechaI']));
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      if($form_state->getValue(['fechaF'])){
        $filtro = true;
        $ultima = array();
        foreach ($rows as $id => $value) {
          if(str_starts_with($value[6], $form_state->getValue(['fechaF']))){
            $form['test'] = array('#markup' => $form_state->getValue(['fechaF']));
            $ultima[$id] = $value;
          }
        }
        $filas = $ultima;
        $rows = $ultima;
      }
      $form['filtro'] = array('#type' => 'hidden', '#value' => $filtro);
      //Se asignan titulos a cada columna
      $header1 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'coordinador' => t('Coordinador de revisiones'),
        'pentesters' => t('Pentesters'),
        'activos' => t('Activos asignados'),
        'start' => t('Fecha de asignación'),
        'last' => t('Fecha de finalización'),
        'edit' => t('Descargar reporte'),
      ];
      $header2 = [
        'id' => t('ID'),
        'type' => t('Tipo'),
        'coordinador' => t('Coordinador de revisiones'),
        'pentesters' => t('Pentesters'),
        'activos' => t('Activos asignados'),
        'start' => t('Fecha de asignación'),
        'last' => t('Fecha de finalización'),
        'edit' => t('Descargar reporte'),
        'seguimiento' => t('Mandar a seguimiento'),
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
          '#rows' => $rows,
          '#empty' => t('Sin revisiones aprobadas.'),
        ];
      }
      
      $form['concluidas'] = [
        '#type' => 'item',
        '#title' => t('Revisiones concluidas'),
        '#markup' => render($concluidas),
      ];
      
      if(!$form_state->getValue(['filtro'])){
        $form['pager'] = array('#type' => 'pager');
      }
    }else{
      return array('#markup' => "No tienes permiso para ver esta página",);
    }
    return $form;
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }
}