<?php
/*
 * @file
 * Contains \Drupal\estadisticas\Form\EstadisticasForm
 */
namespace Drupal\estadisticas\Form;

//require 'spreadsheet/vendor/autoload.php';

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\File;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Drupal\Core\Link; // This should be added in controller
//use Drupal\Core\Url;
/*
 * 
 */
class EstadisticasForm extends FormBase {
	/*
	 *
	 */
	public function getFormId(){
		return 'estadisticas_form';
	}
	/*     
	 * (@inheritdoc)
	 */
	public function buildForm(array $form, FormStateInterface $form_state){
		/*$connection = \Drupal\Core\Database\Database::getConnection();
		$select = $connection->select('dependencias', 'r');
		$select->fields('r', array('id_dependencia', 'nombre_dependencia'));
		$select->orderBy('nombre_dependencia');
		$results = $select->execute();
		foreach($results as $result){
			$options[$result->id_dependencia] = $result->nombre_dependencia;
		}*/
		$form_state->disableCache();
		/*
		 */
		/*$form['browser'] = array(
			'#type' => 'fieldset', 
			'#title' => t('En esta página puede dar de alta sitios.'), 
			'#collapsible' => TRUE, 
			'#description' => t("Llene los campos solicitados o cargue un archivo CSV"), 	
		); 
		$form['description'] = array(
			'#title' => t('Descripción del sitio.'),
			'#type' => 'textarea',
			'#size' => 60,
		);
		$form['ip'] = array(
			'#title' => t("<p>Dirección IP del sitio.<br/>"),
			'#type' => 'textfield',
			'#size' => 60,
			'#maxlength' => 128,
		);*/
		$titulos = array(
			"Mostrar gráfica general con los hallazgos más comunes encontrados en las revisiones de seguridad.",
			"Número de revisiones hechas por departamento (por mes y año).",
			"Número de hallazgos con impacto (Critico, Alto, Medio, Bajo y Sin impacto x Mes y/o Año)",
			"Número de revisiones por dependencia. (Por mes y por año)",
			"Número de hallazgos por dependencia. (Por mes y por año)",
			"Estadísticas por sitio.",
			"Estadísticas por hallazgo (Cantidad de ocurrencias x Mes y/o Año)",
			"Estadísticas por Pentester (Cantidad de revisiones concluidas x Mes y/o Año)",
			"Estadísticas por IP o segmento de red (Cantidad de hallazgos identificados)",
			"Filtrar búsquedas por fecha, año, mes, impacto, sitio, etc.",
			"Comparativas por año."
		);
		$num = 0;
		foreach($titulos as $titulo){
			$form["fieldset_$num"] = array(
				'#type' => 'fieldset', 
				'#title' => t("$titulo"), 
				'#open' => TRUE, 
				'#collapsible' => TRUE, 
			); 
			$form["fieldset_$num"]["b_$num"] = array(
				'#type' => 'submit',
				'#value' => t('Descargar graficas'),
				'#name' => "b_$num",
				'#button_type' => 'primary',
			);
			$num++;
		}
		/*$form['alta_file'] = array(
			'#type' => 'submit',
			'#value' => t('Cargar archivo'),
			'#name' => 'alta_file',
			'#button_type' => 'primary',
		);*/
		return $form;
	}
	/*
 	 *
	 */
/*	
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$button_clicked = $form_state->getTriggeringElement()['#name'];
		if($button_clicked == 'alta'){
			$ip = $form_state->getValue('ip');
			if (strlen($form_state->getValue('description')) <= 0) {
				$form_state->setErrorByName('description', $this->t('Se debe agregar una descripcion del sitio'));
			}
			if (strlen($ip) <= 0) {
				$form_state->setErrorByName('ip', $this->t('Se debe ingresar una direccion ip'));
			} else if(!(filter_var($ip, FILTER_VALIDATE_IP) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))){
				$form_state->setErrorByName('ip', $this->t('Se debe ingresar una direccion ip valida'));
			}
			if (strlen($form_state->getValue('enlace')) <= 0) {
				$form_state->setErrorByName('enlace', $this->t('Se debe ingresar una url'));
			}
		} else if($button_clicked == 'alta_file'){
			if ($form_state->getValue('csv_file') == NULL) {
				$form_state->setErrorByName('csv_file', $this->t('No se puede subir el archivo seleccionado'));
			}
		}
	}
	/*
 	 * (@inheritdoc)
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		//conectar a la otra db
		\Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	        $connection = \Drupal\Core\Database\Database::getConnection();

		/*
		//Estadísticas por IP o segmento de red(Cantidad de hallazgos identificados)
		$select = $connection->select('dir_ip', 'ip');
		//se hace join con tablas necesarias
		$select ->join('ip_sitios', 'ips', 'ip.id_ip = ips.id_ip');
		$select ->join('sitios', 's', 's.id_sitio = ips.id_sitio');
		$select ->join('revisiones_sitios', 'rs', 'rs.id_sitio = s.id_sitio');
		$select ->join('revisiones_hallazgos', 'rh', 'rh.id_rev_sitio = rs.id_rev_sitio');
		$select ->join('hallazgos', 'h', 'h.id_hallazgo = rh.id_hallazgo');
		//Se especifican las columnas a leet
		$select->fields('ip', array('dir_ip_sitios'));
		$select->addExpression('COUNT(*)', 'cuenta');
		$select->groupBy('dir_ip_sitios');
		$select->range(0,10);
		$results = $select->execute();
 		*/

		$select = $connection->select('hallazgos', 'h');
		//Se especifican las columnas a leer
		$select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
		//Se cuenta la aparicion de los hallazgos
		$select->addExpression('COUNT(*)', 'count');
		//Se agrupan por nombre
		$select->groupBY('nombre_hallazgo_vulnerabilidad');
		//Se muestran solo los primeros 10 resultados
		$select->range(0,10);
		$results = $select->execute();
		

		// agregamos los valores de la consulta a un arreglo
		$valores = array();
		$valores[0] = ["", "Cantidad"]; 
		$contador = 1;
		foreach($results as $result){
			//$valores["$result->nombre_hallazgo_vulnerabilidad"] = $result->count;
			$valores["$contador"] = [$result->nombre_hallazgo_vulnerabilidad, $result->count];
			$contador++;
		}	
		/* Inicio de Prueba 1, esto ya crea un archivo csv */
		/*
		$response = new Response();
		$response->headers->set('Pragma', 'no-cache');
    		$response->headers->set('Expires', '0');
    		$response->headers->set('Content-Type', 'application/vnd.ms-excel');
    		$response->headers->set('Content-Disposition', 'attachment; filename=demo.xlsx');
		
		//object of the Spreadsheet class to create the excel data
		$spreadsheet = new Spreadsheet();
		
		//add some data in excel cells
		$spreadsheet->setActiveSheetIndex(0)
	     	->setCellValue('A1', 'Domain')
		->setCellValue('B1', 'Category')
		->setCellValue('C1', 'Nr. Pages');

		$spreadsheet->setActiveSheetIndex(0)
 		->setCellValue('A2', 'CoursesWeb.net')
 		->setCellValue('B2', 'Web Development')
 		->setCellValue('C2', '4000');

		$spreadsheet->setActiveSheetIndex(0)
 		->setCellValue('A3', 'MarPlo.net')
 		->setCellValue('B3', 'Courses & Games')
 		->setCellValue('C3', '15000');

		//set style for A1,B1,C1 cells
		$cell_st =[
			'font' =>['bold' => true],
 			'alignment' =>['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
 			'borders'=>['bottom' =>['style'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]
		];
		$spreadsheet->getActiveSheet()->getStyle('A1:C1')->applyFromArray($cell_st);

		//set columns width
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(16);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(18);

		$spreadsheet->getActiveSheet()->setTitle('Simple'); //set a title for Worksheet

		//make object of the Xlsx class to save the excel file
		//$writer = new Xlsx($spreadsheet);
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$fxls ='excel-file_1.xlsx';
		//ob_start();
		$writer->save("public://$fxls");

		$writer1 = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
		//$writer1 = IOFactory::createWriter($spreadsheet, 'csv');
		$writer1->setSheetIndex(0);   // Select which sheet to export.
		$writer1->setDelimiter(';');
		$writer1->save("public://test.csv");
 */


		// nos dice las celdas que se toman para graficar 
		$cont_1 = $contador + 1;
		// nos dice el numero de la celda donde se inserta la grafica
		$cont_2 = $contador + 2;
		$sitio = "Sitio_1";
		$spreadsheet = new Spreadsheet();
		$worksheet = $spreadsheet->getActiveSheet();
		/*$worksheet->fromArray(
		    [
		        ['', "$sitio"],
		        ['Critico', 2],
		        ['Alto', 4],
		        ['Medio', 5],
		        ['Bajo', 1],
		    ]
	    );*/
		$worksheet->fromArray($valores);

		/*$colors = [
		    'c00000', 'ff0000', 'ffff00', '00b050',
	    ];*/

		// Set the Labels for each data series we want to plot
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker
		$dataSeriesLabels1 = [
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1', null, 1), // 2011
		];
		// Set the X-Axis Labels
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker
		
		$xAxisTickValues1 = [
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$'.$contador, null, 4), // Q1 to Q4
		];
		// Set the Data values for each data series we want to plot
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker
		$dataSeriesValues1 = [
			new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$'.$contador, null, 4),
			//new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$'.$cont_1, null, 4, [], null, $colors),
		];
		//$dataSeriesValues1->setFillColor($colors);
	
		// Build the dataseries
		$series1 = new DataSeries(
		    DataSeries::TYPE_PIECHART, // plotType
		    null, // plotGrouping (Pie charts don't have any grouping)
		    range(0, count($dataSeriesValues1) - 1), // plotOrder
		    $dataSeriesLabels1, // plotLabel
		    $xAxisTickValues1, // plotCategory
		    $dataSeriesValues1          // plotValues
		);
	
		// Set up a layout object for the Pie chart
		$layout1 = new Layout();
		$layout1->setShowVal(true);
		//$layout1->setShowPercent(true);

		// Set the series in the plot area
		$plotArea1 = new PlotArea($layout1, [$series1]);
		// Set the chart legend
		$legend1 = new Legend(Legend::POSITION_RIGHT, null, false);
	
		$title1 = new Title("Test $sitio pie");
	
		// Create the chart
		$chart1 = new Chart(
		    'chart1', // name
		    $title1, // title
		    $legend1, // legend
		    $plotArea1, // plotArea
		    true, // plotVisibleOnly
		    DataSeries::EMPTY_AS_GAP, // displayBlanksAs
		    null, // xAxisLabel
		    null   // yAxisLabel - Pie charts don't have a Y-Axis
		);

		
		// Set the position where the chart should appear in the worksheet
		$chart1->setTopLeftPosition('A'.$cont_2);
		$chart1->setBottomRightPosition('H20');
		
		// Add the chart to the worksheet
		$worksheet->addChart($chart1);
	
		// Set the Labels for each data series we want to plot
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker




		/* de a qui en adelante es el segundo chart */
		
		
		
		$dataSeriesLabels2 = [
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1', null, 1), // 2010
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$D$1', null, 1), // 2012
		];
		// Set the X-Axis Labels
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker
		$xAxisTickValues2 = [
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$'.$contador, null, 4), // Q1 to Q4
		];
		// Set the Data values for each data series we want to plot
		//     Datatype
		//     Cell reference for data
		//     Format Code
		//     Number of datapoints in series
		//     Data values
		//     Data Marker
		$dataSeriesValues2 = [
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$5', null, 4),
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$'.$contador, null, 4),
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$5', null, 4),
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$D$2:$D$5', null, 4),
		];

		// Build the dataseries
		$series2 = new DataSeries(
			DataSeries::TYPE_BARCHART, // plotType
			DataSeries::GROUPING_STANDARD, // plotGrouping 
    			//null, // plotGrouping (Donut charts don't have any grouping) Estamos cambiando por columnas
		    range(0, count($dataSeriesValues2) - 1), // plotOrder
		    $dataSeriesLabels2, // plotLabel
		    $xAxisTickValues2, // plotCategory
		    $dataSeriesValues2        // plotValues
		);

		// Set additional dataseries parameters
		//     Make it a vertical column rather than a horizontal bar graph
		$series2->setPlotDirection(DataSeries::DIRECTION_COL);
		
		// Set up a layout object for the Column chart
		$layout2 = new Layout();
		$layout2->setShowVal(true);
		$layout2->setShowCatName(true);

		// Set the series in the plot area
		//$plotArea2 = new PlotArea($layout2, [$series2]);
		$plotArea2 = new PlotArea(null, [$series2]);

		// Set the chart legend
		$legend = new Legend(Legend::POSITION_RIGHT, null, false);

		//$title2 = new Title('Test Donut Chart');
		$title2 = new Title("Test $sitio Column");

		$yAxisLabel = new Title('Value ($k)');

		// Create the chart
		$chart2 = new Chart(
		    'chart2', // name
		    $title2, // title
		    $legend, // legend
		    $plotArea2, // plotArea
		    true, // plotVisibleOnly
		    DataSeries::EMPTY_AS_GAP, // displayBlanksAs
		    null, // xAxisLabel
		    null   // yAxisLabel - Like Pie charts, Donut charts don't have a Y-Axis
		);

		// Set the position where the chart should appear in the worksheet
		$chart2->setTopLeftPosition('I'.$cont_2);
		$chart2->setBottomRightPosition('P20');
	
		// Add the chart to the worksheet
		$worksheet->addChart($chart2);

		//$path = "public://Templates";
		// Save Excel 2007 file
		$filename = "poc_1.xlsx";//$helper->getFilename(__FILE__);
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->setIncludeCharts(true);
		$callStartTime = microtime(true);
		$writer->save("public://Templates/$filename");
		//$helper->logWrite($writer, $filename, $callStartTime);


		// funciona
		//$redirect = new RedirectResponse(Url::fromUserInput("/sites/default/files/Templates/$filename")->toString());
		//$redirect->send();

		//prueba
		//$redirect = new RedirectResponse("/sites/default/files/Templates/$filename");
		//$redirect->send();
		//return $redirect;
		$uri = "/sites/default/files/Templates/$filename";
		//$response = new BinaryFileResponse("$uri");
		//$headers = array(
			        //'Content-Type'     => 'application/pdf',
		//	        'Content-Disposition' => 'attachment;filename="'.$fileName.'"');
		//$response->setContentDisposition('attachment', $filename);
		//$form_state->setResponse($response);

		//return new BinaryFileResponse($uri, 200, $headers, true);

		$content = file_get_contents("public://Templates/$filename");
	        $file_size = strlen($content);
	        header('Content-Description: File Transfer');
		header('Content-Type: xlsx'); //Im assuming it is audio file you can have your own logic to assign content type dynamically for your file types
		header("Content-Disposition: attachment; filename=$filename"); //Im assuming it is audio mp3 file you can have your own logic to  assign file extension dynamically for your files 
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . $file_size);
		//flush();
		
		
		// funciona
		$redirect = new RedirectResponse(Url::fromUserInput("/estadisticas")->toString());
		
		flush();
		echo($content);
		$redirect->send();
		drupal_flush_all_caches();
		$messenger_service = \Drupal::service('messenger');
		$messenger_service->addMessage(t("Se dio de alta el sitio."));
		
/*		$connection = \Drupal\Core\Database\Database::getConnection();
		$messenger_service = \Drupal::service('messenger');
		if($form_state->getTriggeringElement()['#name'] == 'alta_file'){
			$file_csv = $form_state->getValue('csv_file');
			$file_content = \Drupal\file\Entity\File::load($file_csv[0]);
			$file_uri = $file_content->getFileUri();
			$stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($file_uri);
			$file_path = $stream_wrapper_manager->realpath();
			$spreadsheet = IOFactory::load($file_path);
			$sheetData = $spreadsheet->getActiveSheet();
			$rows = array();
			foreach ($sheetData->getRowIterator() as $row) {  
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(FALSE); 
				$cells = [];
				foreach ($cellIterator as $cell) {
					$cells[] = $cell->getValue();
				}
				$rows[] = $cells; 
			}
			array_shift($rows);
			$cont = 0;
			foreach($rows as $row){
				$txn = $connection->startTransaction();
				$cont++;
				try{
					$desc = $row[0];
					$url = $row[1];
					$ip = $row[2];
					$depen = $row[3];
					if(!empty($desc) && !empty($url) && !empty($ip) && !empty($depen)){
						if((filter_var($ip, FILTER_VALIDATE_IP) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))){
							$query_sitios = $connection->insert('sitios')
				  ->fields(array(
					  'descripcion_sitio' => $desc,
					  'url_sitio' => $url,
				  ))
				  ->execute();
							$select_ip = $connection->select('dir_ip', 'd')
			       ->fields('d', array('id_ip'))
			       ->condition("d.dir_ip_sitios", "$ip");
							$results = $select_ip->execute()->fetchAll();
							if(!empty($results)){
								foreach ($results as $record) {
									$id_ip = $record->id_ip;
								}
							} else {
								$id_ip = $connection->insert('dir_ip')
			    ->fields(array(   
				    'dir_ip_sitios' => $ip,
			    )) ->execute();
							}
							$select_dependencia = $connection->select('dependencias', 'd')
					->fields('d', array('id_dependencia'))
					->condition("d.nombre_dependencia", "$depen");
							$results = $select_dependencia->execute()->fetchAll();
							if(!empty($results)){
								foreach ($results as $record) {
									$id_dependencia = $record->id_dependencia;
								}
							} else {
								$id_dependencia = $connection->insert('dependencias')
				     ->fields(array(   
					     'nombre_dependencia' => $depen,
				     )) ->execute();
							}
							$query_ip_sitios = $connection->insert('ip_sitios')
				     ->fields(array(
					     'id_ip' => $id_ip,
					     'id_sitio' => $query_sitios,
				     ))
				     ->execute();
							$query_ip_sitios = $connection->insert('dependencias_sitios')
				     ->fields(array(
					     'id_dependencia' => $id_dependencia,
					     'id_sitio' => $query_sitios,
				     ))
				     ->execute();
							$messenger_service->addMessage(t("Sitio dado de alta, registro: $cont"));
						} else {
							$messenger_service->addError(t("Ingreda una direccion ip valida, registro: $cont"));
						}
					} else {
						$messenger_service->addError(t("Valida la información ingresada, registro: $cont"));
					}
				} catch(Exception $e) {
					$txn->rollBack();
				}
			}
		} else {
			$desc = Html::escape($form_state->getValue('description'));
			$url = Html::escape($form_state->getValue('enlace'));
			$id_depen = $form_state->getValue('select');
			$ip = $form_state->getValue('ip');
			$txn = $connection->startTransaction();
			try{
				$query_sitios = $connection->insert('sitios')
			       ->fields(array(
				       'descripcion_sitio' => $desc,
				       'url_sitio' => $url,
			       ))
			       ->execute();
				$query_ip = $connection->insert('dir_ip')
			   ->fields(array(    
				   'dir_ip_sitios' => $ip,
			   ))    
			   ->execute();
				$query_ip_sitios = $connection->insert('ip_sitios')
				  ->fields(array(
					  'id_ip' => $query_ip,
					  'id_sitio' => $query_sitios,
				  ))
				  ->execute();
				$query_ip_sitios = $connection->insert('dependencias_sitios')
				  ->fields(array(
					  'id_dependencia' => $id_depen,
					  'id_sitio' => $query_sitios,	
				  ))
				  ->execute();
				$messenger_service->addMessage(t("Se dio de alta el sitio."));
			} catch(Exception $e) {
				$txn->rollBack();
			}
		}*/
	}
}
?>
