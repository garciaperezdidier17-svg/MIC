# Configuración de Tareas Programadas para Alertas de Préstamos (Windows)

Este documento detalla los pasos para configurar la ejecución automática del script `procesar_alertas_prestamos.php` utilizando el Programador de tareas (Task Scheduler) de Windows. Al configurar esta tarea, el sistema verificará automáticamente los préstamos y generará las alertas correspondientes, actualizando el estado de los mismos a "Vencido" cuando aplique.

## Requisitos Previos

- Tener PHP configurado en las variables de entorno de Windows (o conocer la ruta exacta al ejecutable de PHP, usualmente `C:\xampp\php\php.exe`).
- Asegurarse de que el script cron esté ubicado en: `C:\xampp\htdocs\MIC\cron\procesar_alertas_prestamos.php`.

## Pasos para la Configuración

1. **Abrir el Programador de tareas:**
   - Presiona la tecla `Windows`.
   - Escribe **Programador de tareas** (o Task Scheduler) y presiona `Enter`.

2. **Crear una nueva Tarea Básica:**
   - En el panel derecho de "Acciones", haz clic en **Crear tarea básica...** (Create Basic Task...).
   - Asigna un nombre a la tarea, por ejemplo: `MIC - Procesar Alertas de Préstamos`.
   - Agrega una descripción opcional: "Verifica fechas de devolución y genera notificaciones en MIC."
   - Haz clic en **Siguiente**.

3. **Configurar el Desencadenador (Trigger):**
   - Selecciona **Diariamente** (Daily) si deseas que se ejecute una vez al día (recomendado, por ejemplo a las 01:00 AM o 08:00 AM). 
   - *Nota:* También puedes configurarlo para ejecutarse varias veces al día seleccionando opciones avanzadas más adelante. El script está diseñado para no generar duplicados aunque se corra múltiples veces.
   - Haz clic en **Siguiente** y configura la hora de inicio.

4. **Configurar la Acción (Action):**
   - Selecciona **Iniciar un programa** (Start a program) y haz clic en **Siguiente**.
   - En **Programa o script** (Program/script), busca y selecciona el ejecutable de PHP:
     `C:\xampp\php\php.exe`
   - En **Agregar argumentos** (Add arguments (optional)), escribe la ruta completa al script PHP. Asegúrate de incluir comillas si la ruta contiene espacios:
     `"C:\xampp\htdocs\MIC\cron\procesar_alertas_prestamos.php"`
   - Haz clic en **Siguiente**.

5. **Finalizar y verificar configuraciones avanzadas:**
   - Marca la casilla **"Abrir el cuadro de diálogo Propiedades de esta tarea al hacer clic en Finalizar"**.
   - Haz clic en **Finalizar**.
   - En la pestaña **General**, selecciona **"Ejecutar tanto si el usuario inició sesión como si no"** y marca la casilla **"Ejecutar con los privilegios más altos"** si el script necesita permisos adicionales.
   - En la pestaña **Desencadenadores (Triggers)**, si lo deseas, puedes editar el desencadenador diario y habilitar **Repetir tarea cada:** seleccionando `1 hora` para que se ejecute más frecuentemente durante el día.

6. **Guardar y Ejecutar de prueba:**
   - Haz clic en **Aceptar**. Te pedirá la contraseña del administrador de Windows para guardar los cambios.
   - Para verificar que funciona, busca la tarea en la biblioteca, haz clic derecho sobre ella y selecciona **Ejecutar** (Run).

## ¿Qué sucede cuando se ejecuta?

1. Verifica todos los préstamos que estén en estado "activo" o "parcialmente devuelto".
2. Si faltan 3 días o 1 día para la devolución, o si vence hoy, registra una alerta en la base de datos (solo una vez).
3. Si la fecha ya pasó, el préstamo se actualiza automáticamente a "vencido" y se genera una notificación para el administrador, además de dejar registro en el módulo de auditoría del sistema.
