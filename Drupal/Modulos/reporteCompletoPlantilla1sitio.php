<?php
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$recID;
    }else{$mes = $fecha['mon'];}
    $nombreArchivo = $fecha['year'] . $mes . '_';
    
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillaCompleto1.docx');
    $no_rev = 1;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
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


    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();
    //$tmp = getdate();
    //$fechaY = $tmp['year'];

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
    $templateWord->setValue('SITIO_WEB',$urlSitio[0]);
    $templateWord->setValue('DIR_IP',$ipSitio[0]);

    $nombreArchivo .= $urlSitio[0].'_REV'.$no_rev.'_Oficio';

    $select = Database::getConnection()->select('revisiones_sitios', 's');
    $select->join('revisiones_hallazgos','h','s.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_rev_sitio_hall'));
    //$select->fields('h', array('impacto_hall_rev'));
    $select->condition('id_revision',$no_rev);
    $select->orderBy('impacto_hall_rev');
    $id_rev_sitio_hall = $select->execute()->fetchCol();
    $hallazgosV = array();
    $recomendacionesCortas = array();
    $recID = 1;
    $c_critico = 0;
    $c_alto = 0;
    $c_medio = 0;
    $c_bajo = 0;
    $c_ni = 0;
    $recomendacionesCortas = array();
    $anexoA = array();
    foreach ($id_rev_sitio_hall as $id) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->join('hallazgos','h', 'r.id_hallazgo = h.id_hallazgo');
      //$select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('descripcion_hall_rev'));
      $select->fields('r', array('recursos_afectador'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->fields('r', array('cvss_hallazgos'));
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h', array('descripcion_hallazgo'));
      $select->fields('h', array('solucion_recomendacion_halazgo'));
      $select->fields('h', array('referencias_hallazgo'));
      $select->fields('h', array('recomendacion_general_hallazgo'));
      $select->fields('h', array('nivel_cvss'));
      $select->fields('h', array('vector_cvss'));
      $select->fields('h', array('r_ejecutivo_hallazgo'));
      $select->fields('h', array('solucion_corta'));
      $select->condition('id_rev_sitio_hall',$id);
      $datos = $select->execute();
      foreach ($datos as $dato) {
        if($dato->impacto_hall_rev >= 9.0){
          $c_critico++;
          $nivelH = 'CRÍTICO';
        }elseif ($dato->impacto_hall_rev >= 7.0) {
          $c_alto++;
          $nivelH = 'ALTO';
        }elseif ($dato->impacto_hall_rev >= 4.0) {
          $c_medio++;
          $nivelH = 'MEDIO';
        }elseif ($dato->impacto_hall_rev >= 0.1) {
          $c_bajo++;
          $nivelH = 'BAJO';
        }else{
          $c_ni++;
          $nivelH = 'SIN IMPACTO';
        }
        if(strlen((string)$recID) == 1){
            $tmp = '0'.$recID;
        }
        $todo = array(
            'ACTIVO' => $urlSitio[0] . ' / ' . $ipSitio[0],
            'nombre_hallazgo' => $dato->nombre_hallazgo_vulnerabilidad,
            'rec_id' => $tmp,
            'nivel_impacto_n' => $dato->impacto_hall_rev,
            'nivel_impacto' => $nivelH,
        );
        array_push($hallazgosV,$todo);
        array_push($anexoA,array(
            'REC'.$tmp,
            $dato->nombre_hallazgo_vulnerabilidad,
            $dato->descripcion_hallazgo,
            $dato->solucion_recomendacion_halazgo,
            $dato->referencias_hallazgo,
            $dato->impacto_hall_rev,
            $nivelH,
            $dato->cvss_hallazgos
        ));
        if(!in_array($dato->solucion_corta, $recomendacionesCortas)){
          array_push($recomendacionesCortas,$dato->solucion_corta);
        }
      }
      $recID++;
    }
    //Conteo número de hallazgos por nivel
    $templateWord->setValue('c_critico',$c_critico);
    $templateWord->setValue('c_alto',$c_alto);
    $templateWord->setValue('c_medio',$c_medio);
    $templateWord->setValue('c_bajo',$c_bajo);
    $templateWord->setValue('c_ni',$c_ni);
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($recomendacionesCortas));
    foreach ($recomendacionesCortas as $recomendacion) {
      $templateWord->setValue('recomendación_general_h',$recomendacion,1);
    }
    //Se reemplaza en la tabla
    $templateWord->cloneRowAndSetValues('ACTIVO', $hallazgosV);
    
    //ANEXO A
    $templateWord->cloneBlock('TABLA_H',sizeof($anexoA));
    foreach ($anexoA as $tabla) {
      $templateWord->setValue('REC',$tabla[0],1);
      $templateWord->setValue('nombre_hallazgo',$tabla[1],1);
      $templateWord->setValue('descripcion_hallazgo',$tabla[2],1);
      $templateWord->setValue('recomendacionG_hallazgo',$tabla[3],1);
      $templateWord->setValue('url_hallazgo',$tabla[4],1);
      $templateWord->setValue('nivel_impacto_n',$tabla[5],1);
      $templateWord->setValue('nivel_impacto',$tabla[6],1);
      $templateWord->setValue('cvss_hallazgo',$tabla[7],1);
    }
    //ANEXO B
    $templateWord->cloneBlock('ANEXOB',sizeof($anexoA));
    $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
    $select->join('hallazgos','h', 'r.id_hallazgo = h.id_hallazgo');
    $select->fields('r', array('id_rev_sitio_hall'));
    $select->fields('r', array('id_hallazgo'));
    $select->fields('r', array('descripcion_hall_rev'));
    $select->fields('r', array('recursos_afectador'));
    $select->fields('r', array('impacto_hall_rev'));
    $select->fields('r', array('cvss_hallazgos'));
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    $select->fields('h', array('descripcion_hallazgo'));
    $select->fields('h', array('solucion_recomendacion_halazgo'));
    $select->fields('h', array('referencias_hallazgo'));
    $select->fields('h', array('recomendacion_general_hallazgo'));
    $select->fields('h', array('nivel_cvss'));
    $select->fields('h', array('vector_cvss'));
    $select->fields('h', array('r_ejecutivo_hallazgo'));
    $select->orderBy('impacto_hall_rev');
    $select->condition('id_rev_sitio_hall',$id_rev_sitio_hall,'IN');
    $datos = $select->execute();
    Database::setActiveConnection();
    $connection = \Drupal::service('database');
    $contadorImg = 1;
    $contadorHallazgo = 1;
    foreach ($datos as $dato) {
      //ANEXO B
      if($dato->impacto_hall_rev >= 9.0){ $nivelH = 'CRÍTICO';
      }elseif ($dato->impacto_hall_rev >= 7.0) { $nivelH = 'ALTO';
      }elseif ($dato->impacto_hall_rev >= 4.0) { $nivelH = 'MEDIO';
      }elseif ($dato->impacto_hall_rev >= 0.1) { $nivelH = 'BAJO';
      }else{ $nivelH = 'SIN IMPACTO'; }
      $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
      $templateWord->setValue('nivel_impacto_n',$dato->impacto_hall_rev,1);
      $templateWord->setValue('nivel_impacto',$nivelH,1);
      $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
      $templateWord->setValue('descripcion_hall_rev',$dato->descripcion_hall_rev,1);
      $templateWord->setValue('RECURSOS','${RECURSOS'.$dato->id_hallazgo.'}',1);
      $templateWord->setValue('RECURSOS','${/RECURSOS'.$dato->id_hallazgo.'}',1);
      $recursosAfectados = explode("\r", $dato->recursos_afectador);
      $bloque = 'RECURSOS'.$dato->id_hallazgo;
      $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
      foreach($recursosAfectados as $rec){
        $templateWord->setValue('recurso_afectado',$rec,1);
      }

      //Cantidad de imágenes
      $select = $connection->select('file_managed', 'fm');
      $select->addExpression('COUNT(id_rev_sh)','file_managed');
      $select->condition('id_rev_sh', $dato->id_rev_sitio_hall);
      $cantidadImagenesHallazgo = $select->execute()->fetchCol();
      $repImg = 'IMAGENES'.$contadorHallazgo;
      $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
      $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
        
      if($cantidadImagenesHallazgo[0]){
        $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
        //Imágenes y descripción
        $select = $connection->select('file_managed', 'fm')
          ->fields('fm', array('filename', 'descripcion'));
        $select->condition('id_rev_sh', $dato->id_rev_sitio_hall);
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
      $contadorHallazgo++;
    }
    //$templateWord->saveAs('reportes/helloWorld.docx');
    $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');