import type { ReactNode } from "react";
import { Head, Link } from "@inertiajs/react";
import { route } from "ziggy-js";

export default function PublicLayout({
    children,
    title,
    actions,
    home = "clientes.index",
}: {
    children: ReactNode;
    title: string;
    actions?: ReactNode;
    home?: string;
}) {
    return (
        <div className="min-h-dvh bg-muted/30 text-foreground">
            <Head title={`${title} · Criocord`} />
            <header className="border-b bg-background">
                <div className="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <Link
                        href={route(home)}
                        aria-label="Criocord, ir al inicio"
                    >
                        <img
                            src="/images/logos/criocord_logo.png"
                            alt="Criocord"
                            className="h-7 w-auto sm:h-9"
                        />
                    </Link>
                    {actions}
                </div>
            </header>
            <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                {children}
            </main>
        </div>
    );
}
