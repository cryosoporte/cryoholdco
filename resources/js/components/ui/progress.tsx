import { Progress as ProgressPrimitive } from "@base-ui/react/progress";
import { cn } from "@/lib/utils";

export function Progress({
    className,
    ...props
}: ProgressPrimitive.Root.Props) {
    return (
        <ProgressPrimitive.Root
            data-slot="progress"
            className={cn("w-full", className)}
            {...props}
        >
            <ProgressPrimitive.Track className="h-1.5 w-full overflow-hidden rounded-full bg-primary/20">
                <ProgressPrimitive.Indicator className="h-full bg-primary transition-all" />
            </ProgressPrimitive.Track>
        </ProgressPrimitive.Root>
    );
}
