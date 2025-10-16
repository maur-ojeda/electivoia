# electivoia/electivoia.py
import reflex as rx

class AuthState(rx.State):
    """Estado de autenticación."""
    email: str = ""
    password: str = ""
    user_role: str = ""
    error: str = ""

    def set_email(self, value: str):
        self.email = value

    def set_password(self, value: str):
        self.password = value

    def login(self):
        # Validación básica
        if not self.email or not self.password:
            self.error = "Email y contraseña son requeridos."
            return
        
        # Inferir rol por dominio (MVP)
        if self.email.endswith("@admin.colegio.edu"):
            self.user_role = "admin"
        elif self.email.endswith("@profesor.colegio.edu"):
            self.user_role = "teacher"
        elif self.email.endswith("@estudiante.colegio.edu"):
            self.user_role = "student"
        elif self.email.endswith("@apoderado.colegio.edu"):
            self.user_role = "guardian"
        else:
            self.error = "Dominio de email no reconocido."
            return

        # Aquí iría la verificación real de contraseña (MVP: cualquier contraseña)
        self.error = ""
        return rx.redirect(f"/{self.user_role}/dashboard")

    def logout(self):
        self.user_role = ""
        self.email = ""
        self.password = ""
        return rx.redirect("/")


# Vistas por rol
def admin_dashboard() -> rx.Component:
    return rx.vstack(
        rx.heading("Panel de Administrador", size="2"),
        rx.text("Gestión de cursos, usuarios y reportes."),
        rx.button("Cerrar sesión", on_click=AuthState.logout),
        spacing="4",
        align="center",
        padding="20px",
    )

def teacher_dashboard() -> rx.Component:
    return rx.vstack(
        rx.heading("Mis Cursos - Profesor", size="2"),
        rx.text("Lista de cursos asignados y alumnos."),
        rx.button("Cerrar sesión", on_click=AuthState.logout),
        spacing="4",
        align="center",
        padding="20px",
    )

def student_dashboard() -> rx.Component:
    return rx.vstack(
        rx.heading("Mis Electivos - Estudiante", size="2"),
        rx.text("Explora, inscríbete y recibe recomendaciones."),
        rx.button("Cerrar sesión", on_click=AuthState.logout),
        spacing="4",
        align="center",
        padding="20px",
    )

def guardian_dashboard() -> rx.Component:
    return rx.vstack(
        rx.heading("Seguimiento - Apoderado", size="2"),
        rx.text("Cursos de tus pupilos."),
        rx.button("Cerrar sesión", on_click=AuthState.logout),
        spacing="4",
        align="center",
        padding="20px",
    )

def login_form() -> rx.Component:
    return rx.center(
        rx.card(
            rx.vstack(
                rx.heading("ElectivoIA", size="1"),
                rx.input(
                    placeholder="Email",
                    on_change=AuthState.set_email,
                    value=AuthState.email,
                ),
                rx.input(
                    type="password",
                    placeholder="Contraseña",
                    on_change=AuthState.set_password,
                    value=AuthState.password,
                ),
                rx.text(AuthState.error, color="red"),
                rx.button("Iniciar sesión", on_click=AuthState.login, width="100%"),
                spacing="4",
                width="100%",
            ),
            width="350px",
        ),
        height="100vh",
    )

# Rutas
app = rx.App()
app.add_page(login_form, route="/")
app.add_page(admin_dashboard, route="/admin/dashboard")
app.add_page(teacher_dashboard, route="/teacher/dashboard")
app.add_page(student_dashboard, route="/student/dashboard")
app.add_page(guardian_dashboard, route="/guardian/dashboard")