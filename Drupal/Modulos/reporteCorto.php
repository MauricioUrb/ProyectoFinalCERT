<? php
/////////////////////////////////////////////////////////////////////////////////
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    //Centro
    $centro = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
    //Izquierdo
    $izquierdo = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT);
    //Derecho
    $derecho = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT);
    //Justificado
    $justificado = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH);
    //Títulos
    $phpWord->addTitleStyle(null, array('size' => 22, 'bold' => true));
    $phpWord->addTitleStyle(1, array('size' => 20, 'color' => '333333', 'bold' => true));
    $phpWord->addTitleStyle(2, array('size' => 16, 'color' => '666666'));
    $phpWord->addTitleStyle(3, array('size' => 14, 'italic' => true));
    $phpWord->addTitleStyle(4, array('size' => 12));
    
    //Portada
    $section = $phpWord->addSection();
    $section->addText(
      '',
      array('name' => 'Tahoma', 'size' => 10)
    );
    //Logo CERT
    $section->addImage(
      'reportes/imagenes/logoCERT.png',
      array(
        'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(5.6),
        'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(7.8),
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
      )
    );
    //Siguiente página: Index
    //header
    $section = $phpWord->addSection();
    $header = $section->addHeader();
    $table = $header->addTable();
    $table->addRow();
    $table->addCell()->addImage('reportes/imagenes/logoCERT.png', array('width' => 45, 'height' => 60, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    
    //$centro = 'pStyle';
    //$phpWord->addParagraphStyle($centro, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    $cell = $table->addCell();
    $cell->addText('DIRECCIÓN GENERAL DE CÓMPUTO Y DE TECNOLOGÍAS DE INFORMACIÓN Y COMUNICACIÓN',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
    $cell->addText('COORDINACIÓN DE SEGURIDAD DE LA INFORMACIÓN/UNAM-CERT',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
    $cell->addText('XXXXXXX.unam.mx / yyy.zzz.www.vvv',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
    
    $table->addCell()->addImage('reportes/imagenes/logoCERT.png', array('width' => 45, 'height' => 60, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    //Indice
    $fontStyle12 = array('size' => 11);
    $section->addTitle('Tabla de contenido', 0);
    $toc = $section->addTOC($fontStyle12,);
    //Pie de pagina
    $footer = $section->addFooter();
    $table = $footer->addTable();
    $table->addRow();
    $cell = $table->addCell(4500);
    $cell->addText('Fecha',array('bold' => true, 'name' => 'Arial', 'size' => 9),$izquierdo);

    $cell = $table->addCell(4500);
    $cell->addText('',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
    $cell->addText('DOCUMENTO CONFIDENCIAL',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);

    $cell = $table->addCell(4500);
    $cell->addPreserveText('Página {PAGE} de {NUMPAGES}',array('bold' => true, 'name' => 'Arial', 'size' => 9), $derecho);
    
    //Siguiente página: Descripción y Alcance
    $section = $phpWord->addSection();
    $section->addTitle('DESCRIPCIÓN', 1);
    $section->addText('Con Base en la circular DGTIC/002/2017 la Coordinación de Seguridad de la Información/UNAM-CERT se encarga de las revisiones de Análisis de vulnerabilidades a sitios Web, que permiten implementar mejores prácticas con la finalidad de minimizar el riesgo de sufrir ataques a los activos de información.', array('name' => 'Arial', 'size' => 11), $justificado);
    //$section->addText('', array('name' => 'Arial', 'size' => 11), $justificado);
    $textrun = $section->addTextRun(array('name' => 'Arial', 'size' => 11, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH));
    $textrun->addText('Para medir el impacto de las vulnerabilidades encontradas se utiliza la calificación establecida a través del ');
    $textrun->addText('Common Vulnerability Scoring System Version 3');
    $footnote = $textrun->addFootnote();
    $footnote->addText('https://www.first.org/cvss/specification-document');
    $textrun->addText('. A partir de la cual se obtiene el puntaje base que se incluye en este reporte.');
    $section->addText('A partir de los resultados de la revisión de seguridad y los hallazgos documentados, los responsables de la aplicación web deberán evaluar la aplicación de las recomendaciones emitidas en el presente reporte o implementar las que considere pertinentes, verificando que la medida correctiva adoptada no genere problemas en la operación de los servicios.', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addTextBreak(1);

    $section->addTitle('ALCANCE', 2);
    //$section->addText('', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addText('Este documento muestra las vulnerabilidades identificadas y una serie de recomendaciones, las cuales permitirán reforzar el nivel de confidencialidad, integridad y disponibilidad del sitio analizado.', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addTextBreak(1);
    $fancyTableStyleName = 'Fancy Table';
    $fancyTableStyle = array('borderSize' => 6, 'borderColor' => '057299', 'cellMargin' => 80, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER, 'cellSpacing' => 50);
    $fancyTableFirstRowStyle = array('borderBottomColor' => '057299');
    //$fancyTableCellStyle = array('valign' => 'center');
    //$fancyTableCellBtlrStyle = array('valign' => 'center', 'textDirection' => \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR);
    //$fancyTableFontStyle = array('bold' => true 'name' => 'Arial', 'size' => 9);
    $phpWord->addTableStyle($fancyTableStyleName, $fancyTableStyle, $fancyTableFirstRowStyle);
    $table = $section->addTable($fancyTableStyleName);
    $table->addRow();
    $table->addCell()->addText('Sitio', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell()->addText('Sitios de la', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell()->addText('URL', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell()->addText('xxxxx.unam.mx', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell()->addText('Dirección IP', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell()->addText('xxx.yyy.www.zzz', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell()->addText('Fecha de revisión', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell()->addText('Marzo de 2021', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $section->addTextBreak(1);

    $cellColSpan = array('gridSpan' => 2, 'valign' => 'center');
    $cellVCentered = array('valign' => 'center');

    $table = $section->addTable($fancyTableStyleName);
    $table->addRow();
    $cell2 = $table->addCell(null,$cellColSpan)->addText('Elaboración de Documento', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addRow();
    $table->addCell(null, $cellVCentered)->addText('Pentester', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell(null, $cellVCentered)->addText('Nombre', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell(null, $cellVCentered)->addText('Revisión', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecho);
    $table->addCell(null, $cellVCentered)->addText('Nombre', array('name' => 'Arial', 'size' => 11), $izquierdo);
    

    $section->addTitle('HALLAZGOS', 1);
    $section->addText('A continuación, se listan los hallazgos encontrados en la revisión, el nivel de riesgo tiene un valor numérico que se pondera de acuerdo con la siguiente tabla:', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addTextBreak(1);
    //Se hacen las consultas para ver las cantidades
    $critico = 1;
    $alto = 1;
    $medio = 1;
    $bajo = 1;
    $sinImpacto = 1;

    $table = $section->addTable($fancyTableStyleName);
    $table->addRow();
    $textrun = $table->addCell()->addTextRun(array('bold' => true, 'name' => 'Arial', 'size' => 11, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    $textrun->addText('Nivel de impacto', array('bold' => true));
    $footnote = $textrun->addFootnote();
    $footnote->addText('https://www.first.org/cvss/specification-document#i5');
    $table->addCell()->addText('Rango de valores', array('bold' => true, 'name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('Número de hallazgos', array('bold' => true, 'name' => 'Arial', 'size' => 11), $centro);
    $table->addRow();
    $table->addCell()->addText('Crítico', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('9.0 a 10', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText($critico, array('name' => 'Arial', 'size' => 11), $centro);
    $table->addRow();
    $table->addCell()->addText('Alto', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('7.0 a 8.9 ', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText($alto, array('name' => 'Arial', 'size' => 11), $centro);
    $table->addRow();
    $table->addCell()->addText('Medio', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('4.0 a 6.9', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText($medio, array('name' => 'Arial', 'size' => 11), $centro);
    $table->addRow();
    $table->addCell()->addText('Bajo', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('0.1 a 3.9', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText($bajo, array('name' => 'Arial', 'size' => 11), $centro);
    $table->addRow();
    $table->addCell()->addText('Sin impacto', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText('0.0', array('name' => 'Arial', 'size' => 11), $centro);
    $table->addCell()->addText($sinImpacto, array('name' => 'Arial', 'size' => 11), $centro);
    $section->addTextBreak(1);

    $section->addText('Los hallazgos se muestran a continuación junto con las recomendaciones asociadas a la aplicación Web.', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addTextBreak(1);
    //Se consultan los hallazgos y sus datos
    $contador = 1;
    /*foreach ($resultados as $result) {
      $section->addTitle($contador . '. ' nombrehallazgo, 3);

      $table = $section->addTable();
      $table->addRow();
      $table->addCell()->addText('Nivel del impacto', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
      $cell = $table->addCell();
      $cell->addText(impacto,array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
      $cell->addText(cvss,array('name' => 'Arial', 'size' => 9),$centro);
      $table->addRow();
      $table->addCell()->addText('Descripción', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
      $table->addCell()->addText(descripcion, array('name' => 'Arial', 'size' => 11), $izquierdo);
      $table->addRow();
      $table->addCell()->addText('Recomendación', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
      $table->addCell()->addText(recomendacion, array('name' => 'Arial', 'size' => 11), $izquierdo);
      $table->addRow();
      $table->addCell()->addText('Referencias', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
      $table->addCell()->addText(referencias, array('name' => 'Arial', 'size' => 11), $izquierdo);
      $section->addTextBreak(1);

      //Activos
      //Consultas
      $section->addText('Recursos vulnerables:', array('bold' => true, 'name' => 'Arial', 'size' => 11), $justificado);
      foreach($recurso as $recursos){
        $section->addListItem($recurso, 0);
      }
      $section->addTextBreak(1);

      //Descripcion del hallazgo
      $section->addText('Descripción breve del hallazgo:', array('bold' => true, 'name' => 'Arial', 'size' => 11), $justificado);
      $section->addText(descripcion, array('name' => 'Arial', 'size' => 11), $justificado);
      $section->addTextBreak(1);

      //Imágenes del hallazgo
      //Consultas
      $contadorImg = 1;
      foreach($imagenes as $imagen){
        $section->addImage($imagen, tamaño y etc);
        $section->addText('Imagen '. $contador . '. Descripción de la imagen.', array('bold' => true, 'name' => 'Arial', 'size' => 8), $centro);
        $section->addTextBreak(2);
        $contadorImg++;
      }
      $section->addTextBreak(2);
      $contador++;
    }*/
    $section->addTitle($contador . '. HALLAZGO n', 3);
    $table = $section->addTable();
    $table->addRow();
    $table->addCell()->addText('Nivel del impacto', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
    $cell = $table->addCell();
    $cell->addText('Alto - 8.3',array('bold' => true, 'name' => 'Arial', 'size' => 9),$centro);
    $cell->addText('AV:N/AC:L/PR:N/UI:N/S:C/C:L/I:L/A:L',array('name' => 'Arial', 'size' => 9),$centro);
    $table->addRow();
    $table->addCell()->addText('Descripción', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
    $table->addCell()->addText('Descripción general del hallazgos', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell()->addText('Recomendación', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
    $table->addCell()->addText('Recomendación general.', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $table->addRow();
    $table->addCell()->addText('Referencias', array('bold' => true, 'name' => 'Arial', 'size' => 11), $derecha);
    $table->addCell()->addText('URLS', array('name' => 'Arial', 'size' => 11), $izquierdo);
    $section->addTextBreak(1);

    $section->addText('Recursos vulnerables:', array('bold' => true, 'name' => 'Arial', 'size' => 11), $justificado);
    $section->addListItem('List Item I', 0);
    $section->addTextBreak(1);
    
    $section->addText('Descripción breve del hallazgo:', array('bold' => true, 'name' => 'Arial', 'size' => 11), $justificado);
    $section->addText('Esto es una descripción', array('name' => 'Arial', 'size' => 11), $justificado);
    $section->addTextBreak(1);

    $contadorImg = 1;
    $section->addImage('reportes/imagenes/logoCERT.png');
    $section->addText('Imagen '. $contadorImg . '. Descripción de la imagen.', array('bold' => true, 'name' => 'Arial', 'size' => 8), $centro);
    $section->addTextBreak(2);




    //$section->addText('', array('name' => 'Arial', 'size' => 11), $justificado);
    //Guardar documento
    $phpWord->getSettings()->setUpdateFields(true);
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('reportes/helloWorld.docx');
    
    /*
    //Leer doc como está y agregar contenido
    $phpWord = \PhpOffice\PhpWord\IOFactory::load('reportes/plantilla.docx');

    $section = $phpWord->addSection();
    $section->addImage('reportes/imagenes/logoCERT.png');
    */
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantilla2.docx');
    $templateWord->cloneBlock('CLONEME', 1);
    //$templateWord->setValue('DELETEME','borrado');
    $templateWord->setValue('nombreReporte','nombre1',1);
    $templateWord->setValue('columna1','valor columna ',1);
    $templateWord->setValue('columna2','otro valor ',1);
    $templateWord->setValue('datos','datos del test ',1);
    $templateWord->saveAs('reportes/helloWorld.docx');
    //$section->addText('', array('name' => 'Arial', 'size' 