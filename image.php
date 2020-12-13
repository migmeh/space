<?php
//error_reporting(E_ERROR | E_WARNING | E_PARSE);
error_reporting(E_ALL ^ E_NOTICE);

/* Parámetros de la petición que se pueden enviar a este script */
// Src: ubicación del archivo de la raíz doc
// ancho: ancho
// altura: altura
// zc: cultivos de zoom (0 ó 1)
// q: calidad (por defecto es 99 y el máximo es 100)
// El ancho o la altura puede ser utilizado
// <img src="/image.php?src=images/image.jpg&altura=150" alt="some image" />
	
	
if( !isset( $_REQUEST[ "src" ] ) ) { die( "no image specified" ); }

// limpiar parametros antes de su uso
$src		= preg_replace( "/^(\.+(\/|))+/", "", $_REQUEST['src'] );
$src		= preg_replace( '/^(s?f|ht)tps?:\/\/[^\/]+/i', '', $src );
@$new_width	= preg_replace( "/[^0-9]/", "", $_REQUEST[ 'ancho' ] );
@$new_height = preg_replace( "/[^0-9]/", "", $_REQUEST[ 'altura' ] );
@$zoom_crop	= preg_replace( "/[^0-9]/", "", $_REQUEST[ 'zc' ] );

if( !isset( $_REQUEST['q'] ) ) { $quality = 90; } else { $quality = preg_replace("/[^0-9]/", "", $_REQUEST['q'] ); }


// Obtener el tipo MIME de src
$mime_type = mime_type( $src );

// Establecer la ruta de directorio de caché (por defecto es ./cache)
// Esto se puede cambiar a una ubicación diferente
$cache_dir = './cache';

// Comprobar para ver si esta imagen se encuentra en la caché ya
	// Hacer seguro existe el directorio caché
	if(!file_exists($cache_dir)) {
		// Dar 777 permisos para que el desarrollador puede sobrescribir
// archivos creados por el usuario del servidor web
		mkdir($cache_dir);
		chmod($cache_dir, 0777);
	}
	
	show_cache_file($cache_dir, $mime_type);
	










// Asegurarse de que el src es gif / jpg / png
if( !valid_src_mime_type( $mime_type ) ) {
	$error = "Invalid src mime type: $mime_type";
	die( $error );
}

// Comprobar para ver si existe la función GD
if(!function_exists('imagecreatetruecolor')) {
	$error = "GD Library Error: imagecreatetruecolor does not exist";
	die( $error );
}


// Obtener la ruta a la imagen del sistema de archivos
$src = $_SERVER['DOCUMENT_ROOT'] . '/' . $src;

if(strlen($src) && file_exists( $src ) ) {

	// Abre la imagen existente
	$image = open_image($mime_type, $src);
	if ($image === false) { die ('no se puede abrirl la imagen : ' . $src ); }		

	// Obtener la anchura y la altura original
	$width = imagesx($image);
	$height = imagesy($image);

	// Generar las proporciones
	if($new_width && !$new_height) {
		$new_height = $height * ($new_width/$width);
	}
	elseif($new_height && !$new_width) {
		$new_width = $width * ($new_height/$height);
	}
	elseif(!$new_width && !$new_height) {
		$new_width = $width;
		$new_height = $height;
	}

	// Crear una nueva imagen de color verdadero
	
	$canvas = imagecreatetruecolor($new_width, $new_height);
	if($mime_type=="image/gif" or $mime_type=="image/png")
	{
		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		
	}
	
	if( $zoom_crop ) {

		$src_x = $src_y = 0;
    	$src_w = $width;
    	$src_h = $height;

        $cmp_x = $width  / $new_width;
        $cmp_y = $height / $new_height;

		// Calcular la coordenada x o y la anchura o la altura de la fuente

        if ( $cmp_x > $cmp_y ) {
        
            $src_w = round( ( $width / $cmp_x * $cmp_y ) );
            $src_x = round( ( $width - ( $width / $cmp_x * $cmp_y ) ) / 2 );
            
        } elseif ( $cmp_y > $cmp_x ) {
        
            $src_h = round( ( $height / $cmp_y * $cmp_x ) );
            $src_y = round( ( $height - ( $height / $cmp_y * $cmp_x ) ) / 2 );
            
        }
        
		imagecopyresampled( $canvas, $image, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h );
		
	} else {
	
		// Copiar y cambiar el tamaño de parte de una imagen con remuestreo
		imagecopyresampled( $canvas, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
		
	}

	// Imagen de salida al navegador basado en el tipo MIME
	show_image( $mime_type, $canvas, $quality, $cache_dir );
	
	// Eliminar la imagen de la memoria
	ImageDestroy( $canvas );
	
} else {

	if( strlen( $src ) ) { echo $src . ' not found.'; } else { echo 'no source specified.'; }
	
}





















/////////////////////////////////////////////////////////
/////////////////////FUNCIONES//////////////////////////
//////////////////////////////////////////////////////



function show_image ($mime_type, $image_resized, $quality, $cache_dir) {
		
	// Comprobar para ver si podemos escribir en el directorio de caché
	$is_writable = 0;
	$cache_file_name = $cache_dir . '/' . get_cache_file($mime_type);        	
	        	
	if( touch( $cache_file_name ) ) {
		// Dar permisos 666 para que el desarrollador
// Puede sobrescribir usuario del servidor web
		chmod( $cache_file_name, 0666 );
		$is_writable = 1;
	} else {
		$cache_file_name = NULL;
		header('Content-type: ' . $mime_type);
	}
	
    if(stristr( $mime_type, 'gif' ) ) {
        imagegif( $image_resized, $cache_file_name );
    } elseif( stristr( $mime_type, 'jpeg' ) ) {
        imagejpeg( $image_resized, $cache_file_name, $quality );
    } elseif( stristr( $mime_type, 'png' ) ) {
        imagepng( $image_resized, $cache_file_name, ceil( $quality / 10 ) );
    }
    
	if( $is_writable ) { show_cache_file( $cache_dir, $mime_type ); }
    
	exit;
	
}














function open_image ($mime_type, $src) {

	if(stristr($mime_type, 'gif')) {
		$image = imagecreatefromgif($src);
    } elseif(stristr($mime_type, 'jpeg')) {
    	$image = imagecreatefromjpeg($src);
	} elseif(stristr($mime_type, 'png')) {
		$image = imagecreatefrompng($src);
    }
    
	return $image;
	
}











function mime_type($file) {
    $types = array(
		"jpg"  => "image/jpeg",
 		"jpeg" => "image/jpeg",
 		"png"  => "image/png",
 		"gif"  => "image/gif",
 		"bmp"  => "image/bmp", 
 		"doc"  => "application/msword",
 		"xls"  => "application/msword",
 		"xml" => "text/xml",
 		"html" => "text/html"
        // etc...
        // truncated due to Stack Overflow's character limit in posts
    );

    $ext = \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));

	$mime_type = $types[$ext];
	if(!strlen($mime_type)) { $mime_type = 'unknown'; }
	
	return($mime_type);
	
}










function valid_src_mime_type ($mime_type) {

	if( preg_match( "/jpg|jpeg|gif|png/i", $mime_type ) ) { return 1; }
	return 0;
	
}





















function show_cache_file ( $cache_dir,$elmime ) {

    $cache_file = get_cache_file($elmime);
    
    if( file_exists( $cache_dir . '/' . $cache_file ) ) {
    
    	// Comprobar si hay actualizaciones
    	$gmdate_mod	= gmdate('D, d M Y H:i:s', filemtime( $cache_dir . '/' . $cache_file ) ) . " GMT";	
		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE' ] ) ) {
		
			$if_modified_since = preg_replace( "/;.*$/", "", $_SERVER[ "HTTP_IF_MODIFIED_SINCE" ] );
			if ( $if_modified_since >= $gmdate_mod ) {
				header( "HTTP/1.1 304 Not Modified" );
				die();
			}
			
		}	    
		
    	// Enviar cabeceras a continuación, mostrar la imagen
    	header( "Content-Type: ".mime_type($cache_file));
    	header( "Last-Modified: " . gmdate('D, d M Y H:i:s', $thumbModified) . " GMT" );
    	header( "Content-Length: " . filesize( $cache_dir . '/' . $cache_file ) );
    	header( "Cache-Control: max-age=9999" );
    	header( "Expires: " . gmdate( "D, d M Y H:i:s", time() + 99999 ) . "GMT"); 
    	readfile( $cache_dir . '/' . $cache_file);
		die();
		
    }
    
}





function get_cache_file ($e) {

	$request_params = $_REQUEST;
	$cachename = $_REQUEST['src'] . $_REQUEST['ancho'] . $_REQUEST['alto'] . $_REQUEST['zc'] . $_REQUEST['q'];
	
	if($e=="image/gif"){$e="gif";}
	if($e=="image/jpg"){$e="jpeg";}
	if($e=="image/jpeg"){$e="jpeg";}
	if($e=="image/png"){$e="png";}

	$cache_file = md5( $cachename ).".".$e;
	return $cache_file;
	
}







?>
