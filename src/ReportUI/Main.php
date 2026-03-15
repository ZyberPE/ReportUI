<?php

declare(strict_types=1);

namespace ReportUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

class Main extends PluginBase{

    private Config $reports;

    public function onEnable(): void{
        $this->saveDefaultConfig();

        @mkdir($this->getDataFolder());

        $this->reports = new Config($this->getDataFolder() . "reports.yml", Config::YAML, []);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if($command->getName() === "report"){

            if(!$sender instanceof Player){
                return true;
            }

            $this->openReportForm($sender);
        }

        if($command->getName() === "viewreports"){

            if(!$sender instanceof Player){
                return true;
            }

            if(!$sender->hasPermission("report.view")){
                return true;
            }

            $this->openReportsMenu($sender);
        }

        return true;
    }

    private function openReportForm(Player $player): void{

        $onlinePlayers = [];
        $names = [];

        foreach($this->getServer()->getOnlinePlayers() as $p){
            if($p->getName() !== $player->getName()){
                $onlinePlayers[] = $p;
                $names[] = $p->getName();
            }
        }

        $form = new CustomForm(function(Player $player, $data) use ($onlinePlayers){

            if($data === null){
                return;
            }

            $target = $onlinePlayers[$data[0]] ?? null;
            $reason = trim($data[1]);

            if($target === null){
                return;
            }

            if($reason === ""){
                $player->sendMessage($this->color($this->getConfig()->getNested("messages.no-reason")));
                return;
            }

            $reports = $this->reports->getAll();

            $reports[] = [
                "reporter" => $player->getName(),
                "reported" => $target->getName(),
                "reason" => $reason
            ];

            $this->reports->setAll($reports);
            $this->reports->save();

            $player->sendMessage($this->color($this->getConfig()->getNested("messages.report-submitted")));

            $notify = $this->getConfig()->getNested("messages.report-notify");

            $notify = str_replace(
                ["{reported}", "{reporter}"],
                [$target->getName(), $player->getName()],
                $notify
            );

            foreach($this->getServer()->getOnlinePlayers() as $online){
                if($online->hasPermission("report.view")){
                    $online->sendMessage($this->color($notify));
                }
            }

        });

        $form->setTitle($this->color($this->getConfig()->getNested("titles.report-form")));
        $form->addDropdown("Select Player", $names);
        $form->addInput("Reason");

        $player->sendForm($form);
    }

    private function openReportsMenu(Player $player): void{

        $reports = $this->reports->getAll();

        if(count($reports) === 0){
            $player->sendMessage($this->color($this->getConfig()->getNested("messages.no-reports")));
            return;
        }

        $form = new SimpleForm(function(Player $player, $data){

            if($data === null){
                return;
            }

            $this->viewReport($player, $data);
        });

        $form->setTitle($this->color($this->getConfig()->getNested("titles.reports-menu")));

        foreach($reports as $report){

            $form->addButton(
                "Reported: " . $report["reported"] .
                "\nReporter: " . $report["reporter"]
            );
        }

        $player->sendForm($form);
    }

    private function viewReport(Player $player, int $id): void{

        $reports = $this->reports->getAll();

        if(!isset($reports[$id])){
            return;
        }

        $report = $reports[$id];

        $content =
            "Reporter: " . $report["reporter"] . "\n" .
            "Reported Player: " . $report["reported"] . "\n\n" .
            "Reason:\n" . $report["reason"];

        $form = new SimpleForm(function(Player $player, $data) use ($id){

            if($data === null){
                return;
            }

            if($data === 0){

                $reports = $this->reports->getAll();

                unset($reports[$id]);

                $this->reports->setAll(array_values($reports));
                $this->reports->save();

                $player->sendMessage($this->color($this->getConfig()->getNested("messages.report-deleted")));
            }

        });

        $form->setTitle($this->color($this->getConfig()->getNested("titles.report-details")));
        $form->setContent($content);

        $form->addButton("Delete Report");
        $form->addButton("Close");

        $player->sendForm($form);
    }

    private function color(string $text): string{
        return str_replace("&", "§", $text);
    }
}
