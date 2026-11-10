import type { ComponentProps } from "react";
import { Link, usePage } from "@inertiajs/react";
import { route } from "ziggy-js";
import { LayoutDashboardIcon, SettingsIcon } from "lucide-react";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";

export function AppSidebar(props: ComponentProps<typeof Sidebar>) {
    const { empresa, user } = usePage<{
        empresa: string | null;
        user: { name: string; email: string } | null;
    }>().props;
    const enlaces = [
        { title: "Inicio", url: route("home"), icon: <LayoutDashboardIcon /> },
        {
            title: "Operaciones",
            url: route("operaciones"),
            icon: <SettingsIcon />,
            items: [{ title: "Serología", url: route("serologia") }],
        },
    ];

    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            className="h-auto py-3"
                            render={<Link href={route("home")} />}
                        >
                            <img
                                src={
                                    empresa === "001"
                                        ? "/images/logos/criocord_logo.png"
                                        : "/images/logos/lazo_de_vida_logo.png"
                                }
                                alt="Logo de la empresa"
                                className="mx-auto w-full max-w-32"
                            />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={enlaces} />
            </SidebarContent>
            <SidebarFooter>{user && <NavUser user={user} />}</SidebarFooter>
        </Sidebar>
    );
}
