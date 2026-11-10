import type { ComponentProps } from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";

const alertVariants = cva(
    "relative grid w-full gap-1 rounded-lg border px-4 py-3 text-sm has-[>svg]:grid-cols-[1rem_1fr] has-[>svg]:gap-x-3 [&>svg]:mt-0.5 [&>svg]:size-4 [&>svg]:text-current",
    {
        variants: {
            variant: {
                default: "bg-card text-card-foreground",
                destructive:
                    "border-destructive/30 bg-destructive/5 text-destructive",
            },
        },
        defaultVariants: { variant: "default" },
    },
);

function Alert({
    className,
    variant,
    ...props
}: ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="alert-title"
            className={cn("col-start-2 font-medium has-[+div]:mb-1", className)}
            {...props}
        />
    );
}

function AlertDescription({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="alert-description"
            className={cn("col-start-2 text-sm leading-relaxed", className)}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
