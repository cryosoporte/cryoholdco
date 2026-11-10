import type { ReactNode } from "react";
import PublicLayout from "@/layouts/public-layout";

export default function LaboratorioLayout({
    children,
    actions,
}: {
    children: ReactNode;
    actions: ReactNode;
}) {
    return (
        <PublicLayout
            title="Mostrador de serología"
            home="laboratorio.index"
            actions={actions}
        >
            {children}
        </PublicLayout>
    );
}
