import face_recognition
import sys
import json

def verificar_rostro(ruta_original, ruta_captura):
    try:
        # Cargar imágenes
        img_referencia = face_recognition.load_image_file(ruta_original)
        img_captura = face_recognition.load_image_file(ruta_captura)

        # Obtener codificaciones (encodings)
        encodings_ref = face_recognition.face_encodings(img_referencia)
        encodings_cap = face_recognition.face_encodings(img_captura)

        if not encodings_ref or not encodings_cap:
            return {"match": False, "error": "No se detectó rostro"}

        # Comparar (tolerancia 0.6 es estándar, menor es más estricto)
        resultado = face_recognition.compare_faces([encodings_ref[0]], encodings_cap[0], tolerance=0.5)
        
        return {"match": bool(resultado[0])}
    except Exception as e:
        return {"match": False, "error": str(e)}

if __name__ == "__main__":
    # Recibir rutas por argumentos desde Laravel
    res = verificar_rostro(sys.argv[1], sys.argv[2])
    print(json.dumps(res))