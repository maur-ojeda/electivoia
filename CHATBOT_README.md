# 🤖 Chatbot IA con Google Gemini

## 📋 Configuración

### 1. Obtener API Key de Google Gemini

1. Ve a [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Haz clic en "Get API Key" o "Create API Key"
3. Copia la API key generada

### 2. Configurar en Local

Agrega la API key a tu archivo `.env.local`:

```bash
GEMINI_API_KEY=tu_api_key_aqui
```

### 3. Configurar en Producción

En el servidor, agrega la API key al archivo `.env.local`:

```bash
ssh root@167.71.23.86
nano /var/www/electivoia/.env.local
```

Agrega esta línea:
```
GEMINI_API_KEY=tu_api_key_aqui
```

Luego limpia la caché:
```bash
cd /var/www/electivoia
php bin/console cache:clear --env=prod
```

## 🎯 Características del Chatbot

El chatbot IA puede:

- ✅ Recomendar cursos según los intereses del estudiante
- ✅ Responder preguntas sobre cursos específicos
- ✅ Proporcionar información sobre profesores y cupos
- ✅ Ayudar a los estudiantes a descubrir nuevos intereses
- ✅ Explicar las categorías y áreas de los cursos

## 💡 Ejemplos de Preguntas

- "¿Qué cursos de ciencias hay disponibles?"
- "Recomiéndame un curso de artes"
- "¿Quién enseña el curso de robótica?"
- "¿Cuántos cupos quedan en el curso de fotografía?"
- "Me gusta la música, ¿qué curso me recomiendas?"

## 🔧 Cómo Funciona

1. El estudiante escribe una pregunta en el chatbot
2. El frontend envía la pregunta a `/api/chatbot`
3. El backend (`GeminiChatbotService`) obtiene información de todos los cursos
4. Se construye un prompt con el contexto de los cursos
5. Se envía a la API de Google Gemini
6. Gemini procesa la pregunta y genera una respuesta personalizada
7. La respuesta se muestra al estudiante

## 📊 Límites de la API Gratuita

- **60 requests por minuto**
- **1,500 requests por día**

Para 100 usuarios, esto es más que suficiente.

## 🚀 Deployment

Los archivos ya están listos. Solo necesitas:

1. Obtener tu API key de Google Gemini
2. Agregarla al `.env.local` en producción
3. Limpiar la caché
4. ¡Listo!

## 🔒 Seguridad

- La API key nunca se expone al frontend
- Todas las llamadas pasan por el backend
- El chatbot solo tiene acceso a información pública de los cursos
