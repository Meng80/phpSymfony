<?php

namespace App\Entity;

use DateTime;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity, ORM\Table(name: "results")]
#[Serializer\XmlNamespace(uri: "http://www.w3.org/2005/Atom", prefix: "atom")]
#[Serializer\AccessorOrder(order: 'custom', custom: [ "id", "result", "user", "time","_links" ]) ]
class Result implements JsonSerializable
{

    public final const RESULT_ATTR = 'result';
    public final const TIME_ATTR = 'time';
    public final const USER_ATTR = 'user';

    #[ORM\Column(
        name: "id",
        type: "integer",
        nullable: false
    )]
    #[ORM\Id, ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id;

    #[ORM\Column(
        name: "result",
        type: "integer",
        nullable: false
    )]
    private ?int $result;

    #[ORM\ManyToOne(targetEntity: "User")]
    #[ORM\JoinColumn(
        name: "user_id",
        referencedColumnName: "id",
        onDelete: "CASCADE"
    )]
    private ?User $user;

    #[ORM\Column(
        name: "time",
        type: "datetime",
        nullable: false
    )]
    private ?\DateTime $time;

    public function __construct(int $result = 0, ?User $user = null, ?\Datetime $time = null)
    {
        $this->result = $result;
        $this->user = $user;
        $this->time = $time ?: new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getResult(): ?int
    {
        return $this->result;
    }

    /**
     * @param int
     */
    public function setResult(mixed $result): void
    {
        $this->result = $result;
    }

    public function getTime(): ?DateTime
    {
        return $this->time;
    }

    /**
     * @param DateTime $time
     */
    public function setTime(DateTime $time): void
    {
        $this->time = $time;
    }

    public function setTimeFromString(string $time):static{
        $this->time = \DateTime::createFromFormat('Y-m-d H:i:s',$time);
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array{id: int, result: int, user: int, time: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'result' => $this->result,
            'user' => $this->user,
            'time' => $this->time->format('Y-m-d H:i:s')
        ];
    }
}
