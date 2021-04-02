<?php
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$recID;
    }else{$mes = $fecha['mon'];}
    $nombreArchivo = $fecha['year'] . $mes . '_';
    
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillaCompleto2.docx');
    $rev_id = 3;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }

    $templateWord->setValue('DIA',$fecha['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$fecha['year']);
    
    $nombreArchivo .= 'variosSitios_REV'.$rev_id.'_Oficio';
    
    //Se ordenan los sitios y hallazgos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_id);
    $id_rev_sitio = $select->execute()->fetchCol();
    $sitios = array();
    $hallazgos = array();
    $hall_ord = array();
    foreach ($id_rev_sitio as $sitio) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $impactos = $select->execute();
      $impacto = 0;
      foreach ($impactos as $valor) {
        if($valor > $impacto){
          $impacto = $valor->impacto_hall_rev;
        }
        if(!in_array($dato->id_hallazgo, $hallazgos)) {
          array_push($hallazgos, $valor->id_hallazgo);
        }
      }
      $sitios[$sitio] = $impacto;
    }
    foreach ($hallazgos as $idhallazgo) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $select->condition('id_hallazgo',$idhallazgo);
      $impacto = $select->execute()->fetchCol();
      if(!array_key_exists($id_hallazgo,$hall_ord)){
        $hall_ord[$idhallazgo] = $impacto;
      }elseif ($impacto > $hall_ord[$idhallazgo]) {
        $hall_ord[$idhallazgo] = $impacto;
      }
    }
    arsort($sitios);//id_rev_sitio
    arsort($hall_ord);

    //Se organizan los sitios por id
    $datosSitios = array();
    $contadorSitios = 1;
    $alcance = array();
    $resumenEjecutivo = array();
    foreach ($sitios as $id_rs => $valor) {
      if(strlen((string)$contadorSitios) == 1){
        $sitID = '0'.$contadorSitios;
      }
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_sitio'));
      $select->condition('id_rev_sitio',$id_rs);
      $id_sitio = $select->execute()->fetchCol();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$id_sitio[0]);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$id_sitio[0]);
      $ipSitio = $select->execute()->fetchCol();
      array_push($datosSitios, array(
        'ACT' => $sitID,
        'id_s' => $id_sitio[0],
        'id_rs' => $id_rs,
      ));
      $c_critico = 0;
      $c_alto = 0;
      $c_medio = 0;
      $c_bajo = 0;
      $c_ni = 0;
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_critico++; }
        elseif ($valor >= 7.0) { $c_alto++; }
        elseif ($valor >= 4.0) { $c_medio++; }
        elseif ($valor >= 0.1) { $c_bajo++; }
        else{ $c_ni++; }
      }
      array_push($resumenEjecutivo,array(
        'id_activoR' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_critico' => $c_critico,
        'c_alto' => $c_alto,
        'c_medio' => $c_medio,
        'c_bajo' => $c_bajo,
        'c_ni' => $c_ni,
      ));
      array_push($alcance,array(
        'id_activoA' => $sitID,
        'SITIO_WEB' => $url[0],
        'dir_IP' => $ipSitio[0],
      ));
      $contadorSitios++;
    }
    //Tabla de alcance y resumen ejecutivo
    $templateWord->cloneRowAndSetValues('id_activoR', $resumenEjecutivo);
    $templateWord->cloneRowAndSetValues('id_activoA', $alcance);

    //Se organizan los hallazgos con id y valores
    $contadorHallazgo = 1;
    $datosHallazgos = array();
    foreach ($hall_ord as $idhallazgo => $valor) {
      if(strlen((string)$contadorHallazgo) == 1){
        $recID = '0'.$contadorHallazgo;
      }
      $valores = array();
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $impacto = $select->execute()->fetchCol();
      foreach ($impacto as $value) {
        if(!array_key_exists($value,$valores)){
          array_push($valores, $value);
        }
      }
      array_push($datosHallazgos, array(
        'ID' => 'REC'.$recID,
        'id_hall' => $idhallazgo,
        'valores' => $valores,
      ));
      $contadorHallazgo++;
    }

    //Estado actual de seguridad
    $estadoActual = array();
    foreach ($datosSitios as $sitio) {
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      //id_hallazgos
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $id_h = $select->execute()->fetchCol();
      foreach ($datosHallazgos as $hallazgo) {
        if(in_array($hallazgo['id_hall'], $id_h)){
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
          $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
          $select->fields('r', array('impacto_hall_rev'));
          $select->condition('id_rev_sitio',$sitio['id_rs']);
          $select->condition('r.id_hallazgo',$hallazgo['id_hall']);
          $informacion = $select->execute();
          foreach ($informacion as $info) {
            if($info->impacto_hall_rev >= 9.0){
              $nivelH = 'CRÍTICO';
            }elseif ($info->impacto_hall_rev >= 7.0) {
              $nivelH = 'ALTO';
            }elseif ($info->impacto_hall_rev >= 4.0) {
              $nivelH = 'MEDIO';
            }elseif ($info->impacto_hall_rev >= 0.1) {
              $nivelH = 'BAJO';
            }else{
              $nivelH = 'SIN IMPACTO';
            }
            array_push($estadoActual,array(
              'id_activoE' => $sitio['ACT'],
              'ACTIVO' => $url[0] . '/' . $ipSitio[0],
              'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
              'rec_id' => $hallazgo['ID'],
              'nivel_impacto_n' => $info->impacto_hall_rev,
              'nivel_impacto' => $nivelH,
            ));
          }
        }
      }      
    }
    $templateWord->cloneRowAndSetValues('id_activoE', $estadoActual);

    $solucion_corta = array();
    //Anexo A
    $templateWord->cloneBlock('TABLA_H',sizeof($datosHallazgos));
    foreach ($datosHallazgos as $hallazgo) {
      $templateWord->setValue('REC',$hallazgo['ID'],1);
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h', array('descripcion_hallazgo'));
      $select->fields('h', array('solucion_recomendacion_halazgo'));
      $select->fields('h', array('referencias_hallazgo'));
      $select->fields('h', array('solucion_corta '));
      $select->condition('h.id_hallazgo',$hallazgo['id_hall']);
      $datos = $select->execute();
      foreach ($datos as $dato) {
        $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
        $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
        $templateWord->setValue('recomendacionG_hallazgo',$dato->solucion_recomendacion_halazgo,1);
        $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
        if(!in_array($dato->solucion_corta, $solucion_corta)){array_push($solucion_corta, $dato->solucion_corta);}
      }
      $iteracion = 'DETALLES_HM';
      $templateWord->setValue('DETALLES_H','${'.$iteracion.'}',1);
      $templateWord->setValue('DETALLES_H','${/'.$iteracion.'}',1);
      $templateWord->cloneBlock($iteracion,sizeof($hallazgo['valores']));
      rsort($hallazgo['valores']);
      foreach ($hallazgo['valores'] as $impacto) {
        if($impacto >= 9.0){
          $nivelH = 'CRÍTICO';
        }elseif ($impacto >= 7.0) {
          $nivelH = 'ALTO';
        }elseif ($impacto >= 4.0) {
          $nivelH = 'MEDIO';
        }elseif ($impacto >= 0.1) {
          $nivelH = 'BAJO';
        }else{
          $nivelH = 'SIN IMPACTO';
        }
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->fields('r', array('cvss_hallazgos'));
        $select->condition('impacto_hall_rev',(string)$impacto);
        $select->condition('id_rev_sitio',array_keys($sitios),'IN');
        $cvss = $select->execute()->fetchCol();
        $templateWord->setValue('nivel_impacto_n',$impacto,1);
        $templateWord->setValue('nivel_impacto',$nivelH,1);
        $templateWord->setValue('cvss_hallazgo',$cvss[0],1);
      }
    }
    //Recomendaciones generales
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($solucion_corta));
    foreach ($solucion_corta as $solucion) {
      $templateWord->setValue('recomendación_general_h',$solucion,1);
    }
    Database::setActiveConnection();

    //Anexo B
    $templateWord->cloneBlock('ANEXOB',sizeof($datosSitios));
    foreach ($datosSitios as $sitio) {
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      $templateWord->setValue('idSitio',$sitio['ACT'],1);
      $templateWord->setValue('SITIO_WEB',$url[0],1);
      $templateWord->setValue('DIR_IP',$ipSitio[0],1);
      //Se busca la cantidad de hallazgos por sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_hall = $select->execute()->fetchCol();
      $cantidad = 'HALLAZGOBA';
      $templateWord->setValue('HALLAZGOB','${'.$cantidad.'}',1);
      $templateWord->setValue('HALLAZGOB','${/'.$cantidad.'}',1);
      $templateWord->cloneBlock($cantidad,sizeof($id_hall));
      foreach ($id_hall as $id) {
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
        $select->fields('r',array('id_rev_sitio_hall'));
        $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('r',array('impacto_hall_rev'));
        $select->fields('h',array('descripcion_hallazgo'));
        $select->fields('r',array('descripcion_hall_rev'));
        $select->fields('r',array('recursos_afectador'));
        $select->condition('r.id_hallazgo',(string)$id);
        $select->condition('id_rev_sitio',$sitio['id_rs']);
        $results = $select->execute();
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        foreach ($results as $result) {
          if($result->impacto_hall_rev >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($result->impacto_hall_rev >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($result->impacto_hall_rev >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($result->impacto_hall_rev >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $templateWord->setValue('nombre_hallazgo',$result->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('nivel_impacto_n',$result->impacto_hall_rev,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('descripcion_hallazgo',$result->descripcion_hallazgo,1);
          $templateWord->setValue('descripcion_hall_rev',$result->descripcion_hall_rev,1);
          $recursosAfectados = explode("\r", $result->recursos_afectador);
          $bloque = 'RECURSOS1';
          $templateWord->setValue('RECURSOS','${'.$bloque.'}',1);
          $templateWord->setValue('RECURSOS','${/'.$bloque.'}',1);
          $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
          foreach($recursosAfectados as $rec){
            $templateWord->setValue('recurso_afectado',$rec,1);
          }
          //Cantidad de imágenes
          $select = $connection->select('file_managed', 'fm');
          $select->addExpression('COUNT(id_rev_sh)','file_managed');
          $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
          $cantidadImagenesHallazgo = $select->execute()->fetchCol();
          $repImg = 'IMAGENESH';
          $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
          $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
            
          if($cantidadImagenesHallazgo[0]){
            $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
            //Imágenes y descripción
            $select = $connection->select('file_managed', 'fm')
              ->fields('fm', array('filename', 'descripcion'));
            $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
            $imagenesHallazgo = $select->execute();
            foreach ($imagenesHallazgo as $imagenH) {
              $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
              $templateWord->setValue('contadorImg',$contadorImg,1);
              $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
              $contadorImg++;
            }
          }/*else{
            $templateWord->deleteBlock($repImg);
          }*/
        }
      }
    }

    //$templateWord->saveAs('reportes/helloWorld.docx');
    $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');