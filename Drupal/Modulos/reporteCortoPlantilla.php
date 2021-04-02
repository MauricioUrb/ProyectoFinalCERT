<?php
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaCorto.docx');
    ini_set("pcre.backtrack_limit", "-1");
    //$no_rev = 1;
    Database::setActiveConnection('drupaldb_segundo');
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
    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentesters',$nombre,1);
    }
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();
    $templateWord->setValue('nombre_coordinador',$coordinador[0]);
    
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
    $fecha = getdate();
    $fecha_fin = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];

    $templateWord->setValue('sitio_web',$urlSitio[0]);
    $templateWord->setValue('dir_ip',$ipSitio[0]);
    $templateWord->setValue('fecha_fin_revision',$fecha_fin);
    $templateWord->setValue('fecha_hoy',$fecha['year']);
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$recID;
    }else{$mes = $fecha['mon'];}

    $nombreArchivo = $fecha['year'].$mes.'_'.$urlSitio[0].'_REV'.$no_rev.'_Circular';

    $c_critico = 0;
    $c_alto = 0;
    $c_medio = 0;
    $c_bajo = 0;
    $c_ni = 0;
    //hallazgos relacionados
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('revisiones_hallazgos','h','r.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_hallazgo'));
    $select->condition('id_revision',$no_rev);
    $idHallazgos = $select->execute();
    $numHallazgos = 0;
    foreach ($idHallazgos as $hallazgo) {$numHallazgos++;}
    $contadorHallazgo = 1;
    $contadorImg = 1;
    $templateWord->cloneBlock('HALLAZGO', $numHallazgos);
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('revisiones_hallazgos','h','r.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_rev_sitio_hall'));
    $select->fields('h', array('id_rev_sitio'));
    $select->fields('h', array('id_hallazgo'));
    $select->fields('h', array('impacto_hall_rev'));
    $select->fields('h', array('cvss_hallazgos'));
    $select->fields('h', array('recursos_afectador'));
    $select->fields('h', array('descripcion_hall_rev'));
    $select->condition('id_revision',$no_rev);
    $select->orderBy('impacto_hall_rev','DESC');
    $idHallazgos = $select->execute();
    $templateWord->setValue('NOMBRE_HALLAZGO','Actualizar manualmente',1);
    foreach ($idHallazgos as $hallazgo) {
      //Identificación del nivel del hallazgo
      if($hallazgo->impacto_hall_rev >= 9.0){
        $c_critico++;
        $nivelH = 'Crítico - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 7.0) {
        $c_alto++;
        $nivelH = 'Alto - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 4.0) {
        $c_medio++;
        $nivelH = 'Medio - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 0.1) {
        $c_bajo++;
        $nivelH = 'Bajo - ' . $hallazgo->impacto_hall_rev;
      }else{
        $c_ni++;
        $nivelH = 'Sin impacto - ' . $hallazgo->impacto_hall_rev;
      }
      //Datos del hallazgo en el catálogo
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h',array('descripcion_hallazgo'));
      $select->fields('h',array('recomendacion_general_hallazgo'));
      $select->fields('h',array('nivel_cvss'));
      $select->fields('h',array('vector_cvss'));
      $select->fields('h',array('referencias_hallazgo'));
      $select->condition('id_hallazgo',$hallazgo->id_hallazgo);
      $datosHallazgos = $select->execute();
      foreach ($datosHallazgos as $dato) {
        $templateWord->setValue('NOMBRE_HALLAZGO',$contadorHallazgo . '. ' . $dato->nombre_hallazgo_vulnerabilidad,1);
        $templateWord->setValue('nivel_impacto',$nivelH,1);
        $templateWord->setValue('cvss_hallazgo',$hallazgo->cvss_hallazgos,1);
        $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
        $templateWord->setValue('recomendacionG_hallazgo',$dato->recomendacion_general_hallazgo,1);
        $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
      }
      $templateWord->setValue('descripcion_hallazgo_r',$hallazgo->descripcion_hall_rev,1);
      $templateWord->setValue('RECURSOS','${RECURSOS'.$hallazgo->id_hallazgo.'}',1);
      $templateWord->setValue('RECURSOS','${/RECURSOS'.$hallazgo->id_hallazgo.'}',1);
      $recursosAfectados = explode("\r", $hallazgo->recursos_afectador);
      $bloque = 'RECURSOS'.$hallazgo->id_hallazgo;
      $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
      foreach($recursosAfectados as $rec){
        $templateWord->setValue('recurso_afectado',$rec,1);
      }
      //Conteo de la cantidad de imágenes relacionadas a este hallazgo
      Database::setActiveConnection();
      $connection = \Drupal::service('database');
      //Cantidad de imágenes
      $select = $connection->select('file_managed', 'fm');
      $select->addExpression('COUNT(id_rev_sh)','file_managed');
      $select->condition('id_rev_sh', $hallazgo->id_rev_sitio_hall);
      $cantidadImagenesHallazgo = $select->execute()->fetchCol();

      $repImg = 'IMAGENES'.$contadorHallazgo;
      $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
      $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
      $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);

      //Imágenes y descripción
      $select = $connection->select('file_managed', 'fm')
        ->fields('fm', array('filename', 'descripcion'));
      $select->condition('id_rev_sh', $hallazgo->id_rev_sitio_hall);
      $imagenesHallazgo = $select->execute();
      foreach ($imagenesHallazgo as $imagenH) {
        $templateWord->setImageValue('imagen_revision',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
        $templateWord->setValue('contadorImg',$contadorImg,1);
        $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
        $contadorImg++;
      }
      
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      
      $contadorHallazgo++;
    }
    Database::setActiveConnection();
    //Conteo número de hallazgos por nivel
    $templateWord->setValue('c_critico',$c_critico);
    $templateWord->setValue('c_alto',$c_alto);
    $templateWord->setValue('c_medio',$c_medio);
    $templateWord->setValue('c_bajo',$c_bajo);
    $templateWord->setValue('c_ni',$c_ni);
    

    //$templateWord->saveAs('reportes/helloWorld.docx');
    $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');