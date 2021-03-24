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
		if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('pentester', \Drupal::currentUser()->getRoles())){
		//conectar a la otra db
		\Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	        $connection = \Drupal\Core\Database\Database::getConnection();

		// consulta para traer los sitios
		$select = $connection->select('sitios', 's');
		$select->fields('s', array('id_sitio', 'url_sitio'));
		$select->orderBy('url_sitio');
		$results = $select->execute();

		$options = array();
		$options['vacio'] = "--Selecciona un sitio--";
		// acomodamos el resultado de la consulta en un arreglo
		foreach($results as $result){
			$options[$result->id_sitio] = $result->url_sitio;
		}

		$form_state->disableCache();

		// grafica 1	
		$titulo = "Hallazgos más comunes encontrados en las revisiones de seguridad.";
		$form["hallazgos_comunes"] = array(
			'#type' => 'fieldset', 
			'#title' => t("$titulo"), 
			'#open' => TRUE, 
			'#collapsible' => TRUE, 
		); 
		$form["hallazgos_comunes"]["button"] = array(
			'#type' => 'submit',
			'#value' => t('Descargar graficas'),
			'#name' => "hallazgos_comunes",
			'#button_type' => 'primary',
		);
		// titulos de las graficas que requieren fechas
		$titulos = array(
			"Número de revisiones por dependencia.",
			"Número de hallazgos por dependencia.",
			"Número de hallzagos con impacto.",
			"Cantidad de hallazgos encontrados en periodo de tiempo",
		);
		$buttons = array(
			'revisiones_dependencia',
			'hallazgos_dependencia',
			'hallazgos_impacto',
			'hallazgos_date',
		);
		$num = 0;
		// graficas de la 2 a la 5
		foreach($titulos as $titulo){
			$form["date_$num"] = array(
				'#type' => 'fieldset', 
				'#title' => t("$titulo"), 
				'#open' => TRUE, 
				'#collapsible' => TRUE, 
				'#tree' => TRUE,
			); 
			$form["date_$num"]['fecha1'] = array (
				'#type' => 'date',
				'#title' => t('Fecha inicial'),
				'#default_value' => '',
				'#date_date_format' => 'Y/m/d',
				'#description' => date('d/m/Y', time()),
			);
			$form["date_$num"]['fecha2'] = array (
				'#type' => 'date',
				'#title' => t('Fecha final'),
				'#default_value' => '',
				'#date_date_format' => 'Y/m/d',
				'#description' => date('d/m/Y', time()),
			);
			$form["date_$num"]['submit'] = array(
				'#type' => 'submit',
				'#value' => t('Descargar graficas'),
				'#name' => $buttons[$num],
				'#button_type' => 'primary',
			);
			$num++;
		}
		// grafica 6
		$titulo = "Estadisticas por sitio";
		$form["sites"] = array(
			'#type' => 'fieldset', 
			'#title' => t("$titulo"), 
			'#open' => TRUE, 
			'#collapsible' => TRUE, 
			'#tree' => TRUE,
		); 
		$form['sites']['sitio'] = array (
			'#type' => 'select',
			'#title' => t('Listado de sitios'),
			'#options' => $options,
			'#description' => "Selecciona un sitio",
		);
		$form['sites']['submit'] = array(
			'#type' => 'submit',
			'#value' => t('Descargar graficas'),
			'#name' => "sitio",
			'#button_type' => 'primary',
		);
		// grafica 7
		$titulo = "Hallazgos identificados por ip.";
		$form["hallazgos_ip"] = array(
			'#type' => 'fieldset', 
			'#title' => t("$titulo"), 
			'#open' => TRUE, 
			'#collapsible' => TRUE, 
		); 
		$form["hallazgos_ip"]["button"] = array(
			'#type' => 'submit',
			'#value' => t('Descargar graficas'),
			'#name' => "hallazgos_ip",
			'#button_type' => 'primary',
		);

		return $form;
		
		}
    	else{
      		return array('#markup' => "No tienes permiso para ver estos formularios.",);
    	}
	}
	/*
 	 *
	 */
	
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$button_clicked = $form_state->getTriggeringElement()['#name'];

		$buttons_list = [
			'revisiones_dependencia' => 'date_0',
			'hallazgos_dependencia' => 'date_1',
			'hallazgos_impacto' => 'date_2',
			'hallazgos_date' => 'date_3',
		];
		// validacion de los elementos ingresados
		if(array_key_exists($button_clicked, $buttons_list)){
			$field = $buttons_list["$button_clicked"];
			$date1 = $form_state->getValue(["$field",'fecha1']);
			$date2 = $form_state->getValue(["$field",'fecha2']);
			
			if(empty($date1) || empty($date2)){
				$form_state->setErrorByName("$field", $this->t("Se deben de ingresar ambas fechas"));
			} else if(strtotime($date1) > strtotime($date2)){
				$form_state->setErrorByName("$field", $this->t("La fecha inicial '$date1' es mayor a la fecha final '$date2'"));
			}	
		} else if($button_clicked == "sitio"){
			$sitio = $form_state->getValue(['sites', 'sitio']); 
			if($sitio == "vacio"){
				$form_state->setErrorByName('sites', t("Se debe seleccionar un sitio."));
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

		// verificamos que boton se presiono, con esto sabemos que se va a graficar
		$button_clicked = $form_state->getTriggeringElement()['#name'];

		// arreglo que contendra los valores a greficar
		$valores = array();
		$contador = 3;

		$titulo_chart = "Top 10 ";
		switch($button_clicked){
		case "revisiones_dependencia":
			// nombre del archivo
			$name = "revisiones_dependencia.xlsx"; 

			// obtenemos las fechas
			$date1 = $form_state->getValue(['date_0','fecha1']);
			$date2 = $form_state->getValue(['date_0','fecha2']);
			$date1 = str_replace("-","/",$date1);
			$date2 = str_replace("-","/",$date2);

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Revisiones de dependencias ($date1 - $date2)";
			
			// titulo que tendran las graficas
			$titulo_chart .= "revisiones ($date1 - $date2)";
			
			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];
			
			// realizamos la consulta
			$select = $connection->select('dependencias', 'd');
			//se hace join con tablas necesarias
			$select->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
			$select->join('sitios', 's', 's.id_sitio = ds.id_sitio');
			$select->join('revisiones_sitios', 'rs', 'rs.id_sitio = s.id_sitio');
			$select->join('revisiones', 'r', 'r.id_revision = rs.id_revision');
			//Se especifican las columnas a leer
			$select->fields('d', array('nombre_dependencia'));
			$select->addExpression('COUNT(*)', 'cuenta');
			$select->condition('fecha_inicio_revision', array($date1, $date2), 'BETWEEN');
			$select->groupBy('nombre_dependencia');
			$select->orderBy('cuenta', 'DESC');
			$results = $select->execute();

			// agregamos los valores de la consulta a un arreglo
			foreach($results as $result){
				$valores["$contador"] = [$result->nombre_dependencia, $result->cuenta];
				$contador++;
			}	

			break;
		case "hallazgos_comunes":
			// nombre del archivo
			$name = "hallazgos_comunes.xlsx"; 

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Hallazgos más comunes en revisiones";

			// titulo que tendran las graficas
			$titulo_chart .= "hallazgos más comunes";

			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];

			$select = $connection->select('hallazgos', 'h');
			$select->join('revisiones_hallazgos', 'rh', 'h.id_hallazgo = rh.id_hallazgo');
			$select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
			$select->addExpression('COUNT(*)', 'cuenta');
			$select->groupBy('nombre_hallazgo_vulnerabilidad');
			$select->orderBy('cuenta', 'DESC');
			$results = $select->execute();

			// agregamos los valores de la consulta a un arreglo
			foreach($results as $result){
				$valores["$contador"] = [$result->nombre_hallazgo_vulnerabilidad, $result->cuenta];
				$contador++;
			}	

			break;
		case "hallazgos_dependencia":
			// nombre del archivo
			$name = "hallazgos_dependencia.xlsx"; 

			// obtenemos las fechas
			$date1 = $form_state->getValue(['date_1','fecha1']);
			$date2 = $form_state->getValue(['date_1','fecha2']);
			$date1 = str_replace("-","/",$date1);
			$date2 = str_replace("-","/",$date2);

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Hallazgos en dependencias ($date1 - $date2)";
			
			// titulo que tendran las graficas
			$titulo_chart .= "dependencias con más hallazgos ($date1 - $date2)";
			
			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];

			//Número de hallazgos por dependencia. (Por mes y por año)
			$select = $connection->select('dependencias', 'd');
			//se hace join con tablas necesarias
			$select->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
			$select->join('sitios', 's', 's.id_sitio = ds.id_sitio');
			$select->join('revisiones_sitios', 'rs', 'rs.id_sitio = s.id_sitio');
			$select->join('revisiones_hallazgos', 'rh', 'rh.id_rev_sitio = rs.id_rev_sitio');
			$select->join('hallazgos', 'h', 'h.id_hallazgo = rh.id_hallazgo');
			$select->join('revisiones', 'r', 'r.id_revision = rs.id_revision');
	                //Se especifican las columnas a leer
			$select->fields('d', array('nombre_dependencia'));
			$select->addExpression('COUNT(*)', 'cuenta');
			$select->condition('fecha_inicio_revision', array($date1, $date2), 'BETWEEN');
			$select->groupBy('nombre_dependencia');
			$select->orderBy('cuenta', 'DESC');
			$results = $select->execute();

			// agregamos los valores de la consulta a un arreglo
			foreach($results as $result){
				$valores["$contador"] = [$result->nombre_dependencia, $result->cuenta];
				$contador++;
			}	
			break;

		case 'sitio':
			// nombre del archivo
			$name = "sitio.xlsx"; 

			// id del sitio
			$id_sitio = $form_state->getValue(['sites','sitio']);

			// consulta para traer los sitios
			$select = $connection->select('sitios', 's');
			$select->fields('s', array('url_sitio'));
			$select->condition('id_sitio', $id_sitio);
			$results = $select->execute();

			foreach($results as $result){
				$sitio = $result->url_sitio;
			}	
			
			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Estadisticas del sitio $sitio";
			
			// titulo que tendran las graficas
			$titulo_chart = "Sitio '$sitio'";
			
			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];
			
			$select = $connection->select('revisiones_hallazgos', 'rh');
			$select->join('revisiones_sitios', 'rs', 'rs.id_rev_sitio = rh.id_rev_sitio');
			$select->join('revisiones', 'r', 'r.id_revision = rs.id_revision');
			$select->fields('rh', array('impacto_hall_rev'));
			$select->condition('rs.id_sitio', $id_sitio);
			$results = $select->execute();

			// criticidad
			$critico = array(
				"CRITICO" => 0,
				"ALTO" => 0,
				"MEDIO" => 0,
				"BAJO" => 0,
				"SIN IMPACTO" => 0,
			);
			// obtenemos los resultados de las consultas
			foreach($results as $result){
				$nivel = explode(" ", $result->impacto_hall_rev);
				$critico["$nivel[1]"] += 1;
			}

			foreach($critico as $level => $value){
				$valores[$contador] = ["$level", "$value"];
				$contador++;
			}

			break;

		case 'hallazgos_impacto':
			// nombre del archivo
			$name = "hallazgos_impacto.xlsx"; 

			// obtenemos las fechas
			$date1 = $form_state->getValue(['date_2','fecha1']);
			$date2 = $form_state->getValue(['date_2','fecha2']);
			$date1 = str_replace("-","/",$date1);
			$date2 = str_replace("-","/",$date2);

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Hallazgos por impacto ($date1 - $date2)";
			
			// titulo que tendran las graficas
			$titulo_chart = "Cantidad de hallazgos ($date1 - $date2)";
			
			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];
			
			$select = $connection->select('revisiones_hallazgos', 'rh');
			$select->join('revisiones_sitios', 'rs', 'rs.id_rev_sitio = rh.id_rev_sitio');
			$select->join('revisiones', 'r', 'r.id_revision = rs.id_revision');
			$select->fields('rh', array('impacto_hall_rev'));
			$select->condition('fecha_inicio_revision', array($date1, $date2), 'BETWEEN');
			$results = $select->execute();

			// criticidad
			$critico = array(
				"CRITICO" => 0,
				"ALTO" => 0,
				"MEDIO" => 0,
				"BAJO" => 0,
				"SIN IMPACTO" => 0,
			);
			// obtenemos los resultados de las consultas
			foreach($results as $result){
				$nivel = explode(" ", $result->impacto_hall_rev);
				$critico["$nivel[1]"] += 1;
			}

			foreach($critico as $level => $value){
				$valores[$contador] = ["$level", "$value"];
				$contador++;
			}

			break;
		case "hallazgos_date":
			// nombre del archivo
			$name = "hallazgos_fecha.xlsx"; 

			// obtenemos las fechas
			$date1 = $form_state->getValue(['date_3','fecha1']);
			$date2 = $form_state->getValue(['date_3','fecha2']);
			$date1 = str_replace("-","/",$date1);
			$date2 = str_replace("-","/",$date2);

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Hallazgos por concurencia ($date1 - $date2)";
			
			// titulo que tendran las graficas
			$titulo_chart .= "hallazgos ($date1 - $date2)";
			
			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];
			
			// realizamos la consulta
			$select = $connection->select('hallazgos', 'h');
			//se hace join con tablas necesarias
			$select ->join('revisiones_hallazgos', 'rh', 'h.id_hallazgo = rh.id_hallazgo');
			$select ->join('revisiones_sitios', 'rs', 'rs.id_rev_sitio = rh.id_rev_sitio');
			$select ->join('revisiones', 'r', 'r.id_revision = rs.id_revision');
			//Se especifican las columnas a leer
			$select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
			$select->addExpression('COUNT(*)', 'cuenta');
			$select->condition('fecha_inicio_revision', array($date1, $date2), 'BETWEEN');
			$select->groupBy('nombre_hallazgo_vulnerabilidad');
			$select->orderBy('cuenta', 'DESC');
			$results = $select->execute();

			// agregamos los valores de la consulta a un arreglo
			foreach($results as $result){
				$valores["$contador"] = [$result->nombre_hallazgo_vulnerabilidad, $result->cuenta];
				$contador++;
			}	

			break;
		case "hallazgos_ip":
			// nombre del archivo
			$name = "hallazgos_ip.xlsx"; 

			// titulo que tendra el grafico hasta arriba
			$titulo_excel = "Hallazgos identificados por IP";

			// titulo que tendran las graficas
			$titulo_chart .= "hallazgos por IP";

			$valores[0] = ["", "$titulo_excel"]; 
			$valores[1] = ["", ""];
			$valores[2] = ["", "Cantidad"];

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
			$select->orderBy('cuenta', 'DESC');
			$results = $select->execute();

			// agregamos los valores de la consulta a un arreglo
			foreach($results as $result){
				$valores["$contador"] = [$result->dir_ip_sitios, $result->cuenta];
				$contador++;
			}	

			break;
		}
		
		/* En esta parte se inicia la graficacion  */	

		// nos dice las celdas que se toman para graficar 
		$cont_1 = $contador + 1;
		// nos dice el numero de la celda donde se inserta la grafica
		$cont_2 = $contador + 2;
		
		$spreadsheet = new Spreadsheet();
		// unimos las celdas que usamos para el titulo del excel
		$spreadsheet->getActiveSheet()->mergeCells("B1:G1");
		$cell_st =[
			'font' =>['bold' => true],
 			'alignment' =>['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
 			'borders'=>['bottom' =>['style'=> \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]]
		];

		$spreadsheet->getActiveSheet()->getStyle('B1:F1')->applyFromArray($cell_st);
		$worksheet = $spreadsheet->getActiveSheet();
		
		$worksheet->fromArray($valores);

		$len_array = count($valores);

		$res = $len_array - 3 < 10 ? $len_array : 13;

		$dataSeriesLabels1 = [
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$3', null, 1), // 2011
		];
		
		$xAxisTickValues1 = [
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$'.$contador, null, 4), // Q1 to Q4
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$4:$A$'.$res, null, 4), // Q1 to Q4
		];
		
		$dataSeriesValues1 = [
			//new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$'.$contador, null, 4),
			new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$4:$B$'.$res, null, 4),
			//new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$'.$cont_1, null, 4, [], null, $colors),
		];
	
		// Build the dataseries
		$series1 = new DataSeries(
		    DataSeries::TYPE_PIECHART, 			// plotType
		    null,	 				// plotGrouping (Pie charts don't have any grouping)
		    range(0, count($dataSeriesValues1) - 1), 	// plotOrder
		    $dataSeriesLabels1, 			// plotLabel
		    $xAxisTickValues1, 				// plotCategory
		    $dataSeriesValues1          		// plotValues
		);
	
		// Set up a layout object for the Pie chart
		$layout1 = new Layout();
		$layout1->setShowVal(true);

		// Set the series in the plot area
		$plotArea1 = new PlotArea($layout1, [$series1]);

		// Set the chart legend
		$legend1 = new Legend(Legend::POSITION_RIGHT, null, false);
	
		$title1 = new Title("$titulo_chart");
	
		// Create the chart
		$chart1 = new Chart(
		    'chart1', 			// name
		    $title1, 			// title
		    $legend1, 			// legend
		    $plotArea1, 		// plotArea
		    true, 			// plotVisibleOnly
		    DataSeries::EMPTY_AS_GAP, 	// displayBlanksAs
		    null, 			// xAxisLabel
		    null   			// yAxisLabel - Pie charts don't have a Y-Axis
		);

		
		// Set the position where the chart should appear in the worksheet
		$chart1->setTopLeftPosition('D3');
		$chart1->setBottomRightPosition('M20');
		
		// Add the chart to the worksheet
		$worksheet->addChart($chart1);
	
		/* de a qui en adelante es el segundo chart */
		$dataSeriesLabels2 = [
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$3', null, 1), // 2010
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$D$1', null, 1), // 2012
		];
		$xAxisTickValues2 = [
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$4:$A$'.$res, null, 4), // Q1 to Q4
		];
		$dataSeriesValues2 = [
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$5', null, 4),
		    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$4:$B$'.$res, null, 4),
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$5', null, 4),
		    //new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$D$2:$D$5', null, 4),
		];

		// Build the dataseries
		$series2 = new DataSeries(
			DataSeries::TYPE_BARCHART, 			// plotType
			DataSeries::GROUPING_STANDARD, 			// plotGrouping 
		    range(0, count($dataSeriesValues2) - 1), 		// plotOrder
		    $dataSeriesLabels2, 				// plotLabel
		    $xAxisTickValues2, 					// plotCategory
		    $dataSeriesValues2        				// plotValues
		);

		// Set additional dataseries parameters
		// Make it a vertical column rather than a horizontal bar graph
		$series2->setPlotDirection(DataSeries::DIRECTION_COL);
		
		// Set up a layout object for the Column chart
		$layout2 = new Layout();
		$layout2->setShowVal(true);
		$layout2->setShowCatName(true);

		// Set the series in the plot area
		$plotArea2 = new PlotArea(null, [$series2]);

		// Set the chart legend
		$legend = new Legend(Legend::POSITION_RIGHT, null, false);

		//$title2 = new Title('Test Donut Chart');
		$title2 = new Title("$titulo_chart");

		$yAxisLabel = new Title('Value ($k)');

		// Create the chart
		$chart2 = new Chart(
		    'chart2', 			// name
		    $title2, 			// title
		    $legend, 			// legend
		    $plotArea2, 		// plotArea
		    true, 			// plotVisibleOnly
		    DataSeries::EMPTY_AS_GAP, 	// displayBlanksAs
		    null, 			// xAxisLabel
		    null   			// yAxisLabel - Like Pie charts, Donut charts don't have a Y-Axis
		);

		// Set the position where the chart should appear in the worksheet
		$chart2->setTopLeftPosition('D22');
		$chart2->setBottomRightPosition('M39');
	
		// Add the chart to the worksheet
		$worksheet->addChart($chart2);

		$date = date("Y-m-d_H-i-s");
		$filename = "$date" . "_" . "$name";
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->setIncludeCharts(true);
		$callStartTime = microtime(true);
		$writer->save("public://Graficas/$filename");

		// se hace la redireccion a donde se encuentra el archivo y se descarga
		$redirect = new RedirectResponse(Url::fromUserInput("/sites/default/files/Graficas/$filename")->toString());
		$redirect->send();

	}
}
?>
