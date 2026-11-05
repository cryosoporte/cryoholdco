import * as React from "react"

import { NavDocuments } from "@/components/nav-documents"
import { NavMain } from "@/components/nav-main"
import { NavSecondary } from "@/components/nav-secondary"
import { NavUser } from "@/components/nav-user"
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar"
import { LayoutDashboardIcon, ListIcon, ChartBarIcon, FolderIcon, UsersIcon, CameraIcon, FileTextIcon, Settings2Icon, CircleHelpIcon, SearchIcon, DatabaseIcon, FileChartColumnIcon, FileIcon, CommandIcon, Settings } from "lucide-react"
import { usePage } from "@inertiajs/react"
import { route } from "ziggy-js"

const data = {
    navMain: [
        {
            title: "Dashboard",
            url: route('home'),
            icon: (
                <LayoutDashboardIcon />
            ),
        },
        {
            title: "Operaciones",
            url: "#",
            icon: (
                <Settings />
            ),
            items: [
                {
                    title: "Serología",
                    url: route('serologia'),
                }
            ]
        },
        {
            title: "Recursos Humanos",
            'url': "#",
            icon: (
                <UsersIcon />
            ),
            items: [
                {
                    title: "Empleados",
                    url: "#",
                },
                {
                    title: "Vacaciones",
                    url: "#",
                }
            ]
        }
    ],
    navClouds: [
        {
            title: "Capture",
            icon: (
                <CameraIcon
                />
            ),
            isActive: true,
            url: "#",
            items: [
                {
                    title: "Active Proposals",
                    url: "#",
                },
                {
                    title: "Archived",
                    url: "#",
                },
            ],
        },
        {
            title: "Proposal",
            icon: (
                <FileTextIcon
                />
            ),
            url: "#",
            items: [
                {
                    title: "Active Proposals",
                    url: "#",
                },
                {
                    title: "Archived",
                    url: "#",
                },
            ],
        },
        {
            title: "Prompts",
            icon: (
                <FileTextIcon
                />
            ),
            url: "#",
            items: [
                {
                    title: "Active Proposals",
                    url: "#",
                },
                {
                    title: "Archived",
                    url: "#",
                },
            ],
        },
    ],
    navSecondary: [
        {
            title: "Ajustes",
            url: "#",
            icon: (
                <Settings2Icon />
            ),
        },
        {
            title: "Ayuda",
            url: "#",
            icon: (
                <CircleHelpIcon />
            ),
        },
        {
            title: "Buscar",
            url: "#",
            icon: (
                <SearchIcon />
            ),
        },
    ],
    documents: [
        {
            name: "Data Library",
            url: "#",
            icon: (
                <DatabaseIcon
                />
            ),
        },
        {
            name: "Reports",
            url: "#",
            icon: (
                <FileChartColumnIcon
                />
            ),
        },
        {
            name: "Word Assistant",
            url: "#",
            icon: (
                <FileIcon
                />
            ),
        },
    ],
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { empresa, user } = usePage<{
        empresa: string | null,
        user: {
            name: string,
            email: string
        } | null,
    }>().props

    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            className="data-[slot=sidebar-menu-button]:p-1.5!"
                            render={<a href="#" />}
                        >
                            <img
                                src={empresa === "001"
                                    ? "/images/logos/criocord_logo.png"
                                    : "/images/logos/lazo_de_vida_logo.png"
                                }
                                alt="Logo de la empresa"
                                className="w-full max-w-[120px] mx-auto"
                            />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={data.navMain} />
                {/* <NavDocuments items={data.documents} /> */}
                <NavSecondary items={data.navSecondary} className="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                {user && <NavUser user={user} />}
            </SidebarFooter>
        </Sidebar>
    )
}
