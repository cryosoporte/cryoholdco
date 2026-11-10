import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from "@/components/ui/sidebar";
import { Link, usePage } from "@inertiajs/react";

export function NavMain({
    items,
}: {
    items: {
        title: string;
        url: string;
        icon?: React.ReactNode;
        items?: {
            title: string;
            url: string;
        }[];
    }[];
}) {
    const { url } = usePage();

    const esActivo = (destino: string) => {
        if (destino === "#") return false;

        const base = "http://localhost";
        const rutaActual = new URL(url, base).pathname;
        const rutaDestino = new URL(destino, base).pathname;

        return rutaActual === rutaDestino;
    };

    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                tooltip={item.title}
                                isActive={esActivo(item.url)}
                                render={<Link href={item.url} />}
                                className="data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground"
                            >
                                {item.icon}
                                <span>{item.title}</span>
                            </SidebarMenuButton>

                            {item.items && (
                                <SidebarMenuSub>
                                    {item.items.map((subitem) => (
                                        <SidebarMenuSubItem key={subitem.title}>
                                            <SidebarMenuSubButton
                                                isActive={esActivo(subitem.url)}
                                                render={
                                                    <Link href={subitem.url} />
                                                }
                                                className="data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground"
                                            >
                                                <span>{subitem.title}</span>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    ))}
                                </SidebarMenuSub>
                            )}
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
